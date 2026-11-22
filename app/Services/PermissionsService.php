<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use stdClass;

class PermissionsService
{
    public function __construct(private readonly string $programIdentifier) {}

    public function createIdentifier(array $permissionIdentifierKey): string
    {
        $normalize = static function (array|stdClass $nestedFields, int $depth = 0) use (&$normalize): array {
            if ($depth >= 512) {
                throw new InvalidArgumentException('Identifier nesting is too deep.');
            }

            $fields = (array) $nestedFields;
            foreach ($fields as $key => $value) {
                if (! is_string($key)) {
                    throw new InvalidArgumentException('Identifier keys must be strings.');
                }

                if (is_array($value) || $value instanceof stdClass) {
                    $fields[$key] = (object) $normalize($value, $depth + 1);
                } elseif (is_scalar($value)) {
                    $fields[$key] = (string) $value;
                } else {
                    throw new InvalidArgumentException('Identifier values must be scalars or objects with named fields.');
                }
            }

            ksort($fields, SORT_STRING);

            return $fields;
        };

        $identifier = json_encode([
            'program_identifier' => $this->programIdentifier,
            'object' => $normalize($permissionIdentifierKey),
        ], JSON_THROW_ON_ERROR);

        // keep in sync with the migration
        if (mb_strlen($identifier) > 700) {
            throw new InvalidArgumentException('Identifier exceeds the maximum length of 700 characters.');
        }

        return $identifier;
    }

    public function getScopes(array $permissionIdentifierKey): array
    {
        return DB::table('scopes')
            ->join('scope_grants', 'scopes.id', '=', 'scope_grants.scope_id')
            ->where('scopes.program_identifier', $this->programIdentifier)
            ->where('scope_grants.identifier', $this->createIdentifier($permissionIdentifierKey))
            ->orderBy('scopes.name')
            ->pluck('scopes.name')->all();
    }

    public function addScopes(array $permissionIdentifierKey, array $scope_names): void
    {
        $scope_names = array_values(array_unique($scope_names));

        DB::transaction(function () use ($permissionIdentifierKey, $scope_names): void {
            $ids = DB::table('scopes')->where('program_identifier', $this->programIdentifier)
                ->whereIn('name', $scope_names)->pluck('id')->all();

            if (count($ids) !== count($scope_names)) {
                throw new InvalidArgumentException('All scopes must be registered for this program.');
            }

            if ($ids === []) {
                return;
            }

            $identifier = $this->createIdentifier($permissionIdentifierKey);
            foreach ($ids as $id) {
                DB::table('scope_grants')->insertOrIgnore(['identifier' => $identifier, 'scope_id' => $id]);
            }
        });
    }

    public function removeScopes(array $permissionIdentifierKey, array $scope_names): void
    {
        DB::table('scope_grants')->where('identifier', $this->createIdentifier($permissionIdentifierKey))
            ->whereIn('scope_id',
                DB::table('scopes')->select('id')
                    ->where('program_identifier', $this->programIdentifier)
                    ->whereIn('name', $scope_names)
            )->delete();
    }

    /**
     * Delete scope grants in this program when all supplied identifier fields match.
     *
     * For example, ['user_id' => 0, 'project_id' => 0] matches
     * ['user_id' => 0, 'project_id' => 0, 'other_id' => 2].
     * A different user ID or project ID keeps the grant in place.
     * Nested fields match recursively at the supplied paths. Stored objects may have extra fields.
     * An empty nested filter matches only an empty object.
     *
     * usually used when e.g. user or project are deleted, then the scopes also require deletion
     *
     * @param  array<string, mixed>  $permissionIdentifierKey
     *
     * @throws InvalidArgumentException
     */
    public function deleteScopes(array $permissionIdentifierKey): void
    {
        if ($permissionIdentifierKey === []) {
            throw new InvalidArgumentException('At least one identifier field is required.');
        }

        $pattern = json_decode($this->createIdentifier($permissionIdentifierKey), true, flags: JSON_THROW_ON_ERROR)['object'];
        $programGrants = DB::table('scope_grants')->whereIn('scope_id',
            DB::table('scopes')->select('id')->where('program_identifier', $this->programIdentifier)
        );
        $identifiers = (clone $programGrants)->select('identifier')->distinct()->lazyById(column: 'identifier');

        $identifiers->each(function (object $row) use ($pattern, $programGrants): void {
            $identifier = json_decode($row->identifier, true, flags: JSON_THROW_ON_ERROR);
            if ($this->matchesIdentifierPattern($pattern, $identifier['object'] ?? null)) {
                (clone $programGrants)->where('identifier', $row->identifier)->delete();
            }
        });
    }

    // makes sure that the db value includes at least all values the $pattern holds.
    private function matchesIdentifierPattern(mixed $pattern, mixed $stored): bool
    {
        if (! is_array($pattern) || $pattern === []) {
            return $stored === $pattern;
        }

        if (! is_array($stored)) {
            return false;
        }

        foreach ($pattern as $key => $value) {
            if (! array_key_exists($key, $stored) || ! $this->matchesIdentifierPattern($value, $stored[$key])) {
                return false;
            }
        }

        return true;
    }
}
