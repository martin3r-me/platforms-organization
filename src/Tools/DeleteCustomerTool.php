<?php

namespace Platform\Organization\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Tools\Concerns\HasStandardizedWriteOperations;
use Platform\Organization\Models\OrganizationCustomer;
use Platform\Organization\Tools\Concerns\ResolvesOrganizationTeam;

class DeleteCustomerTool implements ToolContract, ToolMetadataContract
{
    use HasStandardizedWriteOperations;
    use ResolvesOrganizationTeam;

    public function getName(): string
    {
        return 'organization.customers.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /organization/customers/{id} - Löscht einen Kunden (soft delete). Hinweis: Wenn der Kunde verlinkt ist, bitte is_active=false setzen statt löschen.';
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
            ],
            'required' => ['customer_id'],
        ]);
    }

    protected function getAccessAction(): string
    {
        return 'delete';
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

            if (method_exists($customer, 'links') && $customer->links()->exists()) {
                return ToolResult::error('VALIDATION_ERROR', 'Kunde ist verlinkt und kann nicht gelöscht werden. Setze stattdessen is_active=false.');
            }

            $customer->delete();

            return ToolResult::success([
                'id' => $customer->id,
                'message' => 'Kunde gelöscht (soft delete).',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler beim Löschen des Kunden: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['organization', 'customers', 'delete'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => true,
        ];
    }
}
