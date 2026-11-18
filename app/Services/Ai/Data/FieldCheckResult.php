<?php

namespace App\Services\Ai\Data;

class FieldCheckResult
{
    public const STATUS_SESUAI = 'SESUAI';

    public const STATUS_PERLU_DICEK = 'PERLU_DICEK';

    public const STATUS_TIDAK_TERBACA = 'TIDAK_TERBACA';

    public function __construct(
        public string $field,
        public string $label,
        public string $status,
        public ?string $form_value = null,
        public ?string $extracted_value = null,
        public bool $is_match = true,
        public ?string $notes = null
    ) {}

    public function isSesuai(): bool
    {
        return $this->status === self::STATUS_SESUAI;
    }

    public function isPerluDicek(): bool
    {
        return $this->status === self::STATUS_PERLU_DICEK;
    }

    public function isTidakTerbaca(): bool
    {
        return $this->status === self::STATUS_TIDAK_TERBACA;
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_SESUAI => 'Sesuai',
            self::STATUS_PERLU_DICEK => 'Perlu diperiksa',
            self::STATUS_TIDAK_TERBACA => 'Tidak terbaca',
            default => $this->status,
        };
    }

    public function statusIcon(): string
    {
        return match ($this->status) {
            self::STATUS_SESUAI => '✓',
            self::STATUS_PERLU_DICEK => '⚠',
            self::STATUS_TIDAK_TERBACA => '✖',
            default => '?',
        };
    }

    public function badgeClass(): string
    {
        return match ($this->status) {
            self::STATUS_SESUAI => 'ai-field-match',
            self::STATUS_PERLU_DICEK => 'ai-field-mismatch',
            self::STATUS_TIDAK_TERBACA => 'ai-field-unreadable',
            default => 'ai-field-unknown',
        };
    }

    public function toArray(): array
    {
        return [
            'field' => $this->field,
            'label' => $this->label,
            'status' => $this->status,
            'status_label' => $this->statusLabel(),
            'status_icon' => $this->statusIcon(),
            'form_value' => $this->form_value,
            'extracted_value' => $this->extracted_value,
            'is_match' => $this->is_match,
            'notes' => $this->notes,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            field: $data['field'] ?? '',
            label: $data['label'] ?? '',
            status: $data['status'] ?? self::STATUS_PERLU_DICEK,
            form_value: $data['form_value'] ?? null,
            extracted_value: $data['extracted_value'] ?? null,
            is_match: (bool) ($data['is_match'] ?? false),
            notes: $data['notes'] ?? null
        );
    }
}
