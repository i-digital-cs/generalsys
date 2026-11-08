<?php

namespace App\Services\GeneralHouse\Import\Importers;

use App\Models\GeneralHouse\GhCustomer;
use App\Models\GeneralHouse\GhZone;
use App\Services\GeneralHouse\Customers\GhCustomerAddressService;
use App\Services\GeneralHouse\Customers\GhCustomerService;
use App\Services\GeneralHouse\Import\GhImportRowException;
use App\Support\GeneralHouse\GhCustomerType;

class CustomerImporter extends AbstractGhImporter
{
    private const ADDRESS_FIELDS = ['zone_name_en', 'label', 'building', 'street', 'area', 'directions'];

    public function __construct(
        private readonly GhCustomerService $customers,
        private readonly GhCustomerAddressService $addresses,
    ) {}

    public function entityKey(): string
    {
        return 'customers';
    }

    public function modelClass(): string
    {
        return GhCustomer::class;
    }

    public function templateFilename(): string
    {
        return 'general-house-customers-example.xlsx';
    }

    public function headers(): array
    {
        return [
            'type', 'name_ar', 'name_en', 'phone', 'email', 'billing_name', 'billing_address', 'tax_registration_number', 'status', 'notes',
            'zone_name_en', 'label', 'building', 'street', 'area', 'directions', 'is_default',
        ];
    }

    public function requiredHeaders(): array
    {
        return ['type', 'name_ar'];
    }

    public function exampleRows(): array
    {
        return [
            ['B2C', 'أحمد علي', 'Ahmed Ali', '971501234567', 'ahmed@example.com', '', '', '', 'ACTIVE', 'يفضل الاتصال قبل الوصول', 'Dubai City', 'Home', 'Villa 12', 'Al Wasl Road', 'Jumeirah', 'Near the mosque', '1'],
            ['B2C', 'سارة محمد', 'Sara Mohammed', '971502223344', '', '', '', '', 'ACTIVE', '', 'Sharjah', 'Apartment', 'Tower B-402', 'Corniche Street', 'Al Majaz', '', '1'],
            ['B2C', 'خالد حسن', 'Khalid Hassan', '971503334455', 'khalid@example.com', '', '', '', 'ACTIVE', '', '', '', '', '', '', '', ''],
            ['B2B', 'فندق النخيل', 'Palm Hotel', '971044445555', 'laundry@palmhotel.example', 'Palm Hotel LLC', 'Sheikh Zayed Road, Dubai', '100123456700003', 'ACTIVE', 'عميل شركات', 'Dubai City', 'Main kitchen', 'Palm Tower', 'Sheikh Zayed Road', 'Trade Centre', 'Service entrance', '1'],
            ['B2B', 'مطعم الواحة', 'Oasis Restaurant', '', 'accounts@oasis.example', 'Oasis Restaurant LLC', 'Al Ain', '', 'ACTIVE', '', 'Al Ain', 'Restaurant', '', 'Main Street', '', '', '1'],
            ['B2C', 'ليلى إبراهيم', 'Layla Ibrahim', '971505556677', '', '', '', '', 'INACTIVE', '', '', '', '', '', '', '', ''],
        ];
    }

    public function instructionLines(): array
    {
        return [
            __('gh.import.sheet.customers_intro'),
            __('gh.import.sheet.required', ['columns' => 'type, name_ar']),
            __('gh.import.sheet.customer_duplicates'),
            __('gh.import.sheet.customer_identity'),
            __('gh.import.sheet.customer_address'),
            __('gh.import.sheet.status_values'),
            __('gh.import.sheet.max_rows', ['max' => 500]),
        ];
    }

    public function referenceRows(): array
    {
        $rows = [['type', 'status', 'zone_name_en', 'zone_name_ar']];
        $zones = GhZone::query()->orderBy('sort_order')->orderBy('name_en')->limit(200)->get(['name_en', 'name_ar']);
        $types = GhCustomerType::ALL;
        $statuses = ['ACTIVE', 'INACTIVE'];
        $count = max($zones->count(), count($types), count($statuses));
        for ($i = 0; $i < $count; $i++) {
            $rows[] = [$types[$i] ?? '', $statuses[$i] ?? '', $zones[$i]->name_en ?? '', $zones[$i]->name_ar ?? ''];
        }

        return $rows;
    }

    protected function parseRow(array $data, array $context): array
    {
        $phone = $this->text($data, 'phone', 40);
        $email = $this->text($data, 'email', 190);
        if ($email !== null && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new GhImportRowException(__('gh.import.errors.invalid_email'));
        }
        if ($phone === null && $email === null) {
            throw new GhImportRowException(__('gh.import.errors.customer_identity'));
        }

        return [
            'type' => $this->customerType($data),
            'name_ar' => $this->text($data, 'name_ar', 150, true),
            'name_en' => $this->text($data, 'name_en', 150),
            'phone' => $phone,
            'email' => $email === null ? null : mb_strtolower($email),
            'billing_name' => $this->text($data, 'billing_name', 190),
            'billing_address' => $this->text($data, 'billing_address', 500),
            'tax_registration_number' => $this->text($data, 'tax_registration_number', 80),
            'status' => $this->status($data),
            'notes' => $this->text($data, 'notes', 5000),
            'address' => $this->address($data),
        ];
    }

    /** @param  array<string, string>  $data */
    private function customerType(array $data): string
    {
        if (trim($data['type'] ?? '') === '') {
            throw new GhImportRowException(__('gh.import.errors.required', ['field' => 'type']));
        }

        return $this->enum($data, 'type', GhCustomerType::ALL, GhCustomerType::B2C);
    }

    /**
     * @param  array<string, string>  $data
     * @return array<string, mixed>|null
     */
    private function address(array $data): ?array
    {
        $given = array_filter(array_map(fn (string $f) => trim($data[$f] ?? ''), self::ADDRESS_FIELDS)) !== [];
        if (! $given) {
            return null;
        }

        $zoneName = $this->text($data, 'zone_name_en', 120, true);
        $zoneId = GhZone::query()->whereRaw('LOWER(name_en) = ?', [$this->lower($zoneName)])->value('id');
        if ($zoneId === null) {
            throw new GhImportRowException(__('gh.import.errors.zone_not_found', ['name' => $zoneName]));
        }

        return [
            'zone_id' => (int) $zoneId,
            'label' => $this->text($data, 'label', 80, true),
            'building' => $this->text($data, 'building', 120),
            'street' => $this->text($data, 'street', 190),
            'area' => $this->text($data, 'area', 120),
            'directions' => $this->text($data, 'directions', 500),
            'is_default' => in_array($this->lower($data['is_default'] ?? ''), ['1', 'true', 'yes', 'y', 'نعم'], true),
        ];
    }

    protected function duplicateKey(array $payload): string
    {
        if ($payload['phone'] !== null) {
            return 'phone:'.$payload['phone'];
        }

        return 'email:'.$payload['email'].'|'.$payload['type'];
    }

    protected function existsInDatabase(array $payload): bool
    {
        $query = GhCustomer::query();
        if ($payload['phone'] !== null) {
            return $query->where('phone', $payload['phone'])->exists();
        }

        return $query->whereRaw('LOWER(email) = ?', [$payload['email']])->where('type', $payload['type'])->exists();
    }

    protected function persist(array $payload, array $context, int $row, array &$imageErrors): void
    {
        $customer = $this->customers->create($payload);
        if ($payload['address'] !== null) {
            $this->addresses->create($customer, $payload['address']);
        }
    }
}
