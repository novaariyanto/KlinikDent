<?php

namespace Tests\Unit;

use App\Enums\MedicationTypeCode;
use App\Enums\PrescriptionItemKind;
use App\Models\Medicine;
use App\Models\PrescriptionCompoundItem;
use App\Models\PrescriptionItem;
use App\Models\Referral;
use App\Support\Integrations\IntegrationException;
use App\Support\Integrations\SatuSehatFhirPayload;
use App\Support\Integrations\SatuSehatPayloadValidator;
use Tests\TestCase;

class SatuSehatPayloadTest extends TestCase
{
    public function test_medication_non_compound_has_medication_type_nc(): void
    {
        $medicine = new Medicine([
            'id' => 1,
            'name' => 'Amoxicillin 500 mg',
            'kfa_code' => '93004418',
            'unit' => 'kapsul',
        ]);
        $medicine->id = 1;

        $item = new PrescriptionItem([
            'kind' => PrescriptionItemKind::Ready,
            'medication_type' => MedicationTypeCode::NonCompound,
            'medicine_id' => 1,
            'quantity' => 10,
        ]);
        $item->id = 5;
        $item->setRelation('medicine', $medicine);
        $item->setRelation('ingredients', collect());

        [$payload, $localKey] = SatuSehatFhirPayload::medicationFromItem($item, '10000004');

        $this->assertSame('medication:1', $localKey);
        $this->assertSame('Medication', $payload['resourceType']);
        $this->assertSame('NC', $payload['extension'][0]['valueCodeableConcept']['coding'][0]['code']);
        $this->assertSame('93004418', $payload['code']['coding'][0]['code']);
        $this->assertSame(SatuSehatFhirPayload::MEDICATION_TYPE_URL, $payload['extension'][0]['url']);

        SatuSehatPayloadValidator::assert('Medication', $payload, $localKey);
    }

    public function test_medication_compound_uses_ep_and_ingredients(): void
    {
        $medicine = new Medicine([
            'name' => 'Paracetamol 500 mg',
            'kfa_code' => '93001019',
            'unit' => 'tablet',
        ]);
        $medicine->id = 2;

        $ingredient = new PrescriptionCompoundItem([
            'name' => 'Paracetamol',
            'quantity' => 10,
            'unit' => 'TAB',
            'medicine_id' => 2,
        ]);
        $ingredient->setRelation('medicine', $medicine);

        $item = new PrescriptionItem([
            'kind' => PrescriptionItemKind::Compound,
            'medication_type' => MedicationTypeCode::EqualParts,
            'quantity' => 20,
        ]);
        $item->id = 9;
        $item->setRelation('medicine', null);
        $item->setRelation('ingredients', collect([$ingredient]));

        [$payload, $localKey] = SatuSehatFhirPayload::medicationFromItem($item, '10000004');

        $this->assertSame('medication:compound:9', $localKey);
        $this->assertSame('EP', $payload['extension'][0]['valueCodeableConcept']['coding'][0]['code']);
        $this->assertArrayNotHasKey('code', $payload);
        $this->assertNotEmpty($payload['ingredient']);
        $this->assertSame('93001019', $payload['ingredient'][0]['itemCodeableConcept']['coding'][0]['code']);
        $this->assertSame(10.0, (float) $payload['ingredient'][0]['strength']['numerator']['value']);

        SatuSehatPayloadValidator::assert('Medication', $payload, $localKey);
    }

    public function test_medication_compound_sd_uses_strength_per_dose(): void
    {
        $medicine = new Medicine([
            'name' => 'Paracetamol 500 mg',
            'kfa_code' => '91000314',
            'unit' => 'tablet',
        ]);
        $medicine->id = 4;

        $ingredient = new PrescriptionCompoundItem([
            'name' => 'Paracetamol',
            'quantity' => 5,
            'unit' => 'TAB',
            'medicine_id' => 4,
            'strength_value' => 125,
            'strength_unit' => 'mg',
        ]);
        $ingredient->setRelation('medicine', $medicine);

        $item = new PrescriptionItem([
            'kind' => PrescriptionItemKind::Compound,
            'medication_type' => MedicationTypeCode::GiveSuchDoses,
            'quantity' => 20,
        ]);
        $item->id = 11;
        $item->setRelation('medicine', null);
        $item->setRelation('ingredients', collect([$ingredient]));

        [$payload, $localKey] = SatuSehatFhirPayload::medicationFromItem($item, '10000004');

        $this->assertSame('medication:compound:11', $localKey);
        $this->assertSame('SD', $payload['extension'][0]['valueCodeableConcept']['coding'][0]['code']);
        $this->assertSame(125.0, (float) $payload['ingredient'][0]['strength']['numerator']['value']);
        $this->assertSame('mg', $payload['ingredient'][0]['strength']['numerator']['code']);
        $this->assertSame('http://unitsofmeasure.org', $payload['ingredient'][0]['strength']['numerator']['system']);

        SatuSehatPayloadValidator::assert('Medication', $payload, $localKey);
    }

    public function test_medication_compound_sd_without_strength_returns_null(): void
    {
        $medicine = new Medicine(['name' => 'Paracetamol', 'kfa_code' => '91000314']);
        $medicine->id = 5;
        $ingredient = new PrescriptionCompoundItem([
            'name' => 'Paracetamol',
            'quantity' => 5,
            'unit' => 'TAB',
            'medicine_id' => 5,
        ]);
        $ingredient->setRelation('medicine', $medicine);

        $item = new PrescriptionItem([
            'kind' => PrescriptionItemKind::Compound,
            'medication_type' => MedicationTypeCode::GiveSuchDoses,
            'quantity' => 20,
        ]);
        $item->id = 12;
        $item->setRelation('ingredients', collect([$ingredient]));

        $this->assertNull(SatuSehatFhirPayload::medicationFromItem($item, '10000004'));
    }

    public function test_medication_without_type_rejected_by_validator(): void
    {
        $this->expectException(IntegrationException::class);
        $this->expectExceptionMessage('Medication.extension');

        SatuSehatPayloadValidator::assert('Medication', [
            'resourceType' => 'Medication',
            'status' => 'active',
            'code' => [
                'coding' => [[
                    'system' => 'http://sys-ids.kemkes.go.id/kfa',
                    'code' => '93004418',
                    'display' => 'Obat',
                ]],
            ],
        ], 'medication:1');
    }

    public function test_service_request_referral_has_coding(): void
    {
        $referral = new Referral([
            'referred_to' => 'RS Gigi Rujukan',
            'reason' => 'Perawatan spesialistik',
            'notes' => 'Segera',
        ]);
        $referral->id = 10;

        $payload = SatuSehatFhirPayload::serviceRequestReferral(
            $referral,
            '10000004',
            'patient-ihs',
            'practitioner-ihs',
            'encounter-1',
            '2026-10-16T03:00:00+00:00',
        );

        $this->assertSame('ServiceRequest', $payload['resourceType']);
        $this->assertSame('103697008', $payload['code']['coding'][0]['code']);
        $this->assertSame('http://snomed.info/sct', $payload['code']['coding'][0]['system']);
        $this->assertSame('Perawatan spesialistik', $payload['code']['text']);
        $this->assertSame('order', $payload['intent']);

        SatuSehatPayloadValidator::assert('ServiceRequest', $payload, 'servicerequest:referral:10');
    }

    public function test_service_request_without_code_rejected_before_send(): void
    {
        $this->expectException(IntegrationException::class);
        $this->expectExceptionMessage('ServiceRequest.code');

        SatuSehatPayloadValidator::assert('ServiceRequest', [
            'resourceType' => 'ServiceRequest',
            'status' => 'active',
            'intent' => 'order',
            'code' => ['text' => 'Rujukan'],
            'subject' => ['reference' => 'Patient/1'],
        ], 'servicerequest:referral:10');
    }

    public function test_terminology_code_is_not_auto_replaced_with_default(): void
    {
        $payload = [
            'resourceType' => 'ServiceRequest',
            'status' => 'active',
            'intent' => 'order',
            'code' => [
                'coding' => [[
                    'system' => 'http://snomed.info/sct',
                    'code' => '108252007',
                    'display' => 'Laboratory procedure',
                ]],
            ],
            'subject' => ['reference' => 'Patient/1'],
        ];

        SatuSehatPayloadValidator::assert('ServiceRequest', $payload, 'servicerequest:lab:1');

        $this->assertSame('108252007', $payload['code']['coding'][0]['code']);
        $this->assertNotSame('103697008', $payload['code']['coding'][0]['code']);
    }

    public function test_operation_outcome_element_parsed(): void
    {
        $this->assertSame(
            'Medication.extension',
            SatuSehatPayloadValidator::fhirElementFromMessage('Element not found: Medication.extension')
        );
        $this->assertSame(
            'ServiceRequest.code',
            SatuSehatPayloadValidator::fhirElementFromMessage('Element not found: ServiceRequest.code')
        );
    }

    public function test_ready_item_without_kfa_returns_null_not_fake_code(): void
    {
        $medicine = new Medicine(['name' => 'Obat tanpa KFA', 'kfa_code' => null]);
        $medicine->id = 3;

        $item = new PrescriptionItem(['kind' => PrescriptionItemKind::Ready]);
        $item->setRelation('medicine', $medicine);
        $item->setRelation('ingredients', collect());

        $this->assertNull(SatuSehatFhirPayload::medicationFromItem($item, '10000004'));
    }
}
