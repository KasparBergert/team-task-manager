<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PermissionsService
{
    public function __construct(private readonly string $programIdentifier) {
        //scopes are created here   
    }
    
    /* used to create an element with scopes */
    public function createIdentifier(array $object): string
    {
        /**
         * since order and exact letters given to the hash function matters -
         * ordering and only 1 type allowed for keys and values are allowed
         * e.g. if values are allowed to be numbers and strings which represent numbers,
         * then the hash will not be the same, but its expected to.         
         */
        foreach ($object as $key => $value) {
            if (! is_string($key)) {
                throw new InvalidArgumentException('Identifier keys must be strings.');
            }

            if (! is_scalar($value)) {
                throw new InvalidArgumentException('Identifier values must be scalar.');
            }

            $object[$key] = (string) $value;
        }
        ksort($object, SORT_STRING);

        //'2' and 2 are converted to '2'
        $data = json_encode(
            [$this->programIdentifier, $object],
            JSON_THROW_ON_ERROR,
        );

        return hash('sha256', $data);
    }

    public function getScopesFor(array $object): array
    {
        return DB::table('scopes')
            ->join('scope_grants', 'scopes.id', '=', 'scope_grants.scope_id')
            ->where('scopes.program_identifier', $this->programIdentifier)
            ->where('scope_grants.object_hash', $this->createIdentifier($object))
            ->orderBy('scopes.name')
            ->pluck('scopes.name')->all();
    }

    public function addScopes(array $object, array $scope_names): void
    {
        $scope_names = array_values(array_unique($scope_names));

        DB::transaction(function () use ($object, $scope_names): void {
            $ids = DB::table('scopes')->where('program_identifier', $this->programIdentifier)
                ->whereIn('name', $scope_names)->pluck('id')->all();

            if (count($ids) !== count($scope_names)) {
                throw new InvalidArgumentException('All scopes must be registered for this program.');
            }

            $hash = $this->createIdentifier($object);
            foreach ($ids as $id) {
                DB::table('scope_grants')->insertOrIgnore(['object_hash' => $hash, 'scope_id' => $id]);
            }
        });
    }

    public function removeScopes(array $object, array $scope_names): void
    {
        DB::table('scope_grants')->where('object_hash', $this->createIdentifier($object))
            ->whereIn('scope_id',
                // find scopes which are to be removed
                DB::table('scopes')->select('id')
                    ->where('program_identifier', $this->programIdentifier)
                    ->whereIn('name', $scope_names)
            )->delete();
    }
}
