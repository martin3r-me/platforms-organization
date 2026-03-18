<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Organization\Models\OrganizationCustomer;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class UpdateCustomerTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.customers.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /organization/customers/{id} - Aktualisiert einen Kunden im Root/Elterteam. Parameter: customer_id (required).';
    }

    public function getSchema(): array
    {
        return $this->mergeWriteSchema([
            'properties' => [
                'team_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: Team-ID (wird auf Root/Elterteam aufgelöst). Default: Team aus Kontext.',
                ],
                'customer_id' => [
                    'type' => 'integer',
                    'description' => 'ID des Kunden (ERFORDERLICH). Nutze organization.customers.GET.',
                ],
                'code' => [
                    'type' => 'string',
                    'description' => 'Optional: Code ("" zum Leeren).',
                ],
                'name' => [
                    'type' => 'string',
                    'description' => 'Optional: Name.',
                ],
                'description' => [
                    'type' => 'string',
                    'description' => 'Optional: Beschreibung ("" zum Leeren).',
                ],
                'root_entity_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: root_entity_id (0/null zum Globalisieren).',
                ],
                'is_active' => [
                    'type' => 'boolean',
                    'description' => 'Optional: aktiv/inaktiv.',
                ],
                'metadata' => [
                    'type' => 'object',
                    'description' => 'Optional: Metadatenobjekt (null zum Leeren).',
                ],
            ],
            'required' => ['customer_id'],
        ]);
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $resolved = $this->resolveTeamAndRoot($arguments, $context);
            if ($resolved['error']) {
                return $resolved['error'];
            }
            $rootTeamId = (int)$resolved['root_team_id'];

            $found = $this->validateAndFindModel(
                $arguments,
                $context,
                'customer_id',
                OrganizationCustomer::class,
                'NOT_FOUND',
                'Kunde nicht gefunden.'
            );
            if ($found['error']) {
                return $found['error'];
            }

            /** @var OrganizationCustomer $customer */
            $customer = $found['model'];
            if ((int)$customer->team_id !== $rootTeamId) {
                return ToolResult::error('ACCESS_DENIED', 'Kunde gehört nicht zum Root/Elterteam des angegebenen Teams.');
            }

            $update = [];
            if (array_key_exists('code', $arguments)) {
                $code = trim((string)($arguments['code'] ?? ''));
                $update['code'] = $code === '' ? null : $code;
                if ($update['code'] !== null) {
                    $exists = OrganizationCustomer::query()
                        ->where('team_id', $rootTeamId)
                        ->where('code', $update['code'])
                        ->where('id', '!=', $customer->id)
                        ->whereNull('deleted_at')
                        ->exists();
                    if ($exists) {
                        return ToolResult::error('VALIDATION_ERROR', "Kunde mit code '{$update['code']}' existiert bereits im Root/Elterteam.");
                    }
                }
            }
            if (array_key_exists('name', $arguments)) {
                $name = trim((string)($arguments['name'] ?? ''));
                if ($name === '') {
                    return ToolResult::error('VALIDATION_ERROR', 'name darf nicht leer sein.');
                }
                $update['name'] = $name;
            }
            if (array_key_exists('description', $arguments)) {
                $d = (string)($arguments['description'] ?? '');
                $update['description'] = $d === '' ? null : $d;
            }
            if (array_key_exists('root_entity_id', $arguments)) {
                $rid = $arguments['root_entity_id'];
                if ($rid === null || $rid === '' || $rid === 'null' || $rid === 0 || $rid === '0') {
                    $update['root_entity_id'] = null;
                } else {
                    $update['root_entity_id'] = (int)$rid;
                }
            }
            if (array_key_exists('is_active', $arguments)) {
                $update['is_active'] = (bool)$arguments['is_active'];
            }
            if (array_key_exists('metadata', $arguments)) {
                $update['metadata'] = (isset($arguments['metadata']) && is_array($arguments['metadata'])) ? $arguments['metadata'] : null;
            }

            if (!empty($update)) {
                $customer->update($update);
            }
            $customer->refresh();

            return ToolResult::success([
                'id' => $customer->id,
                'code' => $customer->code,
                'name' => $customer->name,
                'team_id' => $customer->team_id,
                'root_entity_id' => $customer->root_entity_id,
                'is_active' => (bool)$customer->is_active,
                'message' => 'Kunde erfolgreich aktualisiert.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Aktualisieren des Kunden: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['organization', 'customers', 'update'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => true,
        ];
    }
}
