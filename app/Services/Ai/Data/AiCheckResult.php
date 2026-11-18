<?php

namespace App\Services\Ai\Data;

use App\Enums\AiCheckStatus;

class AiCheckResult
{
    /**
     * @param  array<string, FieldCheckResult>  $fields
     * @param  array<string, mixed>  $extracted_data
     */
    public function __construct(
        public AiCheckStatus $overall_status,
        public string $summary,
        public array $fields = [],
        public array $extracted_data = [],
        public int $confidence_score = 100,
        public string $provider = 'mock',
        public ?string $checked_at = null,
        public string $disclaimer = 'AI hanya memberikan rekomendasi pemeriksaan. Keputusan akhir tetap dilakukan oleh petugas desa.'
    ) {
        $this->checked_at = $checked_at ?: now()->toIso8601String();
    }

    public function isDataSesuai(): bool
    {
        return $this->overall_status === AiCheckStatus::DATA_SESUAI;
    }

    public function isDataPerluDicek(): bool
    {
        return $this->overall_status === AiCheckStatus::DATA_PERLU_DICEK;
    }

    public function isDokumenTidakTerbaca(): bool
    {
        return $this->overall_status === AiCheckStatus::DOKUMEN_TIDAK_TERBACA;
    }

    public function getField(string $fieldName): ?FieldCheckResult
    {
        return $this->fields[$fieldName] ?? null;
    }

    public function hasDiscrepancies(): bool
    {
        foreach ($this->fields as $field) {
            if ($field->isPerluDicek() || $field->isTidakTerbaca()) {
                return true;
            }
        }

        return false;
    }

    public function toArray(): array
    {
        $fieldsArray = [];
        foreach ($this->fields as $key => $field) {
            $fieldsArray[$key] = $field instanceof FieldCheckResult ? $field->toArray() : (array) $field;
        }

        return [
            'overall_status' => $this->overall_status->value,
            'overall_status_label' => $this->overall_status->label(),
            'overall_status_icon' => $this->overall_status->icon(),
            'summary' => $this->summary,
            'disclaimer' => $this->disclaimer,
            'confidence_score' => $this->confidence_score,
            'provider' => $this->provider,
            'checked_at' => $this->checked_at,
            'fields' => $fieldsArray,
            'extracted_data' => $this->extracted_data,
        ];
    }

    public static function fromArray(array $data): self
    {
        $statusValue = $data['overall_status'] ?? AiCheckStatus::DATA_PERLU_DICEK->value;
        $status = $statusValue instanceof AiCheckStatus
            ? $statusValue
            : (AiCheckStatus::tryFrom($statusValue) ?? AiCheckStatus::DATA_PERLU_DICEK);

        $fields = [];
        if (! empty($data['fields']) && is_array($data['fields'])) {
            foreach ($data['fields'] as $key => $fieldData) {
                $fields[$key] = $fieldData instanceof FieldCheckResult
                    ? $fieldData
                    : FieldCheckResult::fromArray($fieldData);
            }
        }

        return new self(
            overall_status: $status,
            summary: $data['summary'] ?? '',
            fields: $fields,
            extracted_data: $data['extracted_data'] ?? [],
            confidence_score: (int) ($data['confidence_score'] ?? 100),
            provider: $data['provider'] ?? 'mock',
            checked_at: $data['checked_at'] ?? null,
            disclaimer: $data['disclaimer'] ?? 'AI hanya memberikan rekomendasi pemeriksaan. Keputusan akhir tetap dilakukan oleh petugas desa.'
        );
    }
}
