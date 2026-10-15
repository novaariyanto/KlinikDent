<?php

namespace App\Support\Integrations;

final class SatuSehatCodes
{
    public const SNOMED = 'http://snomed.info/sct';

    public const LOINC = 'http://loinc.org';

    public const CLINICAL = 'http://terminology.kemkes.go.id/CodeSystem/clinical-term';

    public const ICD10 = 'http://hl7.org/fhir/sid/icd-10';

    public const ICD9 = 'http://hl7.org/fhir/sid/icd-9-cm';

    public const NIK_SYSTEM = 'https://fhir.kemkes.go.id/id/nik';

    /**
     * Nomenklatur FDI dari lampiran terminologi gigi SATUSEHAT.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    public const TEETH = [
        '11' => ['422653006', 'Structure of permanent maxillary right central incisor tooth'],
        '12' => ['424877001', 'Structure of permanent maxillary right lateral incisor tooth'],
        '13' => ['860767006', 'Structure of permanent maxillary right canine tooth'],
        '14' => ['57826002', 'Structure of permanent maxillary right first premolar tooth'],
        '15' => ['36492000', 'Structure of permanent maxillary right second premolar tooth'],
        '16' => ['865995000', 'Structure of permanent maxillary right first molar tooth'],
        '17' => ['863902006', 'Structure of permanent maxillary right second molar tooth'],
        '18' => ['68085002', 'Structure of permanent maxillary right third molar tooth'],
        '21' => ['424399000', 'Structure of permanent maxillary left central incisor tooth'],
        '22' => ['423185002', 'Structure of permanent maxillary left lateral incisor tooth'],
        '23' => ['860780009', 'Structure of permanent maxillary left canine tooth'],
        '24' => ['61897005', 'Structure of permanent maxillary left first premolar tooth'],
        '25' => ['23226009', 'Structure of permanent maxillary left second premolar tooth'],
        '26' => ['865988009', 'Structure of permanent maxillary left first molar tooth'],
        '27' => ['863901004', 'Structure of permanent maxillary left second molar tooth'],
        '28' => ['87704003', 'Structure of permanent maxillary left third molar tooth'],
        '31' => ['425106001', 'Structure of permanent mandibular left central incisor tooth'],
        '32' => ['423331005', 'Structure of permanent mandibular left lateral incisor tooth'],
        '33' => ['860782001', 'Structure of permanent mandibular left canine tooth'],
        '34' => ['2400006', 'Structure of permanent mandibular left first premolar tooth'],
        '35' => ['24573005', 'Structure of permanent mandibular left second premolar tooth'],
        '36' => ['866006002', 'Structure of permanent mandibular left first molar tooth'],
        '37' => ['863898000', 'Structure of permanent mandibular left second molar tooth'],
        '38' => ['74344005', 'Structure of permanent mandibular left third molar tooth'],
        '41' => ['424575004', 'Structure of permanent mandibular right central incisor tooth'],
        '42' => ['423937004', 'Structure of permanent mandibular right lateral incisor tooth'],
        '43' => ['860785004', 'Structure of permanent mandibular right canine tooth'],
        '44' => ['80140008', 'Structure of permanent mandibular right first premolar tooth'],
        '45' => ['8873007', 'Structure of permanent mandibular right second premolar tooth'],
        '46' => ['866005003', 'Structure of permanent mandibular right first molar tooth'],
        '47' => ['863899008', 'Structure of permanent mandibular right second molar tooth'],
        '48' => ['38994002', 'Structure of permanent mandibular right third molar tooth'],
        '51' => ['88824007', 'Structure of deciduous maxillary right central incisor tooth'],
        '52' => ['65624003', 'Structure of deciduous maxillary right lateral incisor tooth'],
        '53' => ['30618001', 'Structure of deciduous maxillary right canine tooth'],
        '54' => ['17505006', 'Structure of deciduous maxillary right first molar tooth'],
        '55' => ['27855007', 'Structure of deciduous maxillary right second molar tooth'],
        '61' => ['51678005', 'Structure of deciduous maxillary left central incisor tooth'],
        '62' => ['43622005', 'Structure of deciduous maxillary left lateral incisor tooth'],
        '63' => ['73937000', 'Structure of deciduous maxillary left canine tooth'],
        '64' => ['45234009', 'Structure of deciduous maxillary left first molar tooth'],
        '65' => ['51943008', 'Structure of deciduous maxillary left second molar tooth'],
        '71' => ['89552004', 'Structure of deciduous mandibular left central incisor tooth'],
        '72' => ['14770005', 'Structure of deciduous mandibular left lateral incisor tooth'],
        '73' => ['43281008', 'Structure of deciduous mandibular left canine tooth'],
        '74' => ['38896004', 'Structure of deciduous mandibular left first molar tooth'],
        '75' => ['49330006', 'Structure of deciduous mandibular left second molar tooth'],
        '81' => ['67834006', 'Structure of deciduous mandibular right central incisor tooth'],
        '82' => ['22445006', 'Structure of deciduous mandibular right lateral incisor tooth'],
        '83' => ['6062009', 'Structure of deciduous mandibular right canine tooth'],
        '84' => ['58646007', 'Structure of deciduous mandibular right first molar tooth'],
        '85' => ['61868007', 'Structure of deciduous mandibular right second molar tooth'],
    ];

    /**
     * Permukaan gigi, lampiran 4. Kunci mengikuti odontogram KlinikDent.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    public const SURFACES = [
        'mesial' => ['710099007', 'Mesial'],
        'occlusal' => ['257885003', 'Occlusion-action'],
        'distal' => ['46053002', 'Distal'],
        'buccal' => ['302990001', 'Buccalis'],
        'palatal' => ['255579002', 'Palatal-lingual'],
    ];

    /**
     * Keadaan atau restorasi yang punya kode resmi. component: finding|restoration.
     *
     * @var array<string, array{system: string, code: string, display: string, component: string}>
     */
    public const TOOTH_STATUS = [
        'healthy' => ['system' => self::SNOMED, 'code' => '162005007', 'display' => 'No tooth problem', 'component' => 'finding'],
        'caries' => ['system' => self::SNOMED, 'code' => '80967001', 'display' => 'Dental caries', 'component' => 'finding'],
        'filling' => ['system' => self::SNOMED, 'code' => '287451003', 'display' => 'Tooth cavity drilled and filled', 'component' => 'finding'],
        'missing' => ['system' => self::SNOMED, 'code' => '234948008', 'display' => 'Tooth absent', 'component' => 'finding'],
        'extracted' => ['system' => self::SNOMED, 'code' => '234948008', 'display' => 'Tooth absent', 'component' => 'finding'],
        'fracture' => ['system' => self::SNOMED, 'code' => '278590005', 'display' => 'Fractured dental crown', 'component' => 'finding'],
        'impacted' => ['system' => self::SNOMED, 'code' => '129263008', 'display' => 'Impacted tooth', 'component' => 'finding'],
        'crown' => ['system' => self::SNOMED, 'code' => '272289008', 'display' => 'Crown', 'component' => 'restoration'],
        'bridge' => ['system' => self::SNOMED, 'code' => '272299003', 'display' => 'Bridge - dental', 'component' => 'restoration'],
        'implant' => ['system' => self::SNOMED, 'code' => '468993001', 'display' => 'Dental implant system', 'component' => 'restoration'],
        'root_canal' => ['system' => self::SNOMED, 'code' => '706370006', 'display' => 'Root canal post', 'component' => 'restoration'],
    ];

    /**
     * @var array<string, array{0: string, 1: string}>
     */
    public const OCCLUSION = [
        'normal' => ['278683009', 'No malocclusion'],
        'cross' => ['109495004', 'Crossbite'],
        'steep' => ['60476005', 'Deep overbite'],
        'deep' => ['60476005', 'Deep overbite'],
    ];

    /**
     * @var array<string, array{0: string, 1: string}>
     */
    public const TORUS_PALATINUS = [
        'none' => ['OV000074', 'Tidak ada Torus Palatinus'],
        'small' => ['OV000075', 'Torus Palatinus Kecil'],
        'medium' => ['OV000076', 'Torus Palatinus Sedang'],
        'large' => ['OV000077', 'Torus Palatinus Besar'],
        'multiple' => ['OV000078', 'Multiple Torus Palatinus'],
    ];

    /**
     * @var array<string, array{0: string, 1: string}>
     */
    public const TORUS_MANDIBULARIS = [
        'none' => ['OV000079', 'Tidak ada Torus Mandibularis'],
        'left' => ['OV000080', 'Torus Mandibularis Sisi Kiri'],
        'right' => ['OV000081', 'Torus Mandibularis Sisi Kanan'],
        'both' => ['OV000082', 'Torus Mandibularis pada Kedua Sisi'],
    ];

    /**
     * @var array<string, array{0: string, 1: string}>
     */
    public const PALATE = [
        'deep' => ['OV000083', 'Palatum Dalam'],
        'medium' => ['OV000084', 'Palatum Sedang'],
        'low' => ['OV000085', 'Palatum Rendah'],
    ];

    /**
     * @var array<string, array{0: string, 1: string}>
     */
    public const PRESENCE = [
        'none' => ['OV000086', 'Tidak Ada Diastema'],
        'present' => ['OV000087', 'Ada Diastema'],
    ];

    /**
     * @var array<string, array{0: string, 1: string}>
     */
    public const ANOMALY = [
        'none' => ['OV000088', 'Tidak Ada Gigi Anomali'],
        'present' => ['OV000089', 'Ada Gigi Anomali'],
    ];

    /**
     * @return array{0: string, 1: string}|null
     */
    public static function tooth(string $number): ?array
    {
        return self::TEETH[$number] ?? null;
    }

    /**
     * @return array{system: string, code: string, display: string}
     */
    public static function coding(string $system, string $code, string $display): array
    {
        return [
            'system' => $system,
            'code' => $code,
            'display' => $display,
        ];
    }

    /**
     * @param  array{system: string, code: string, display: string}  $coding
     * @return array{coding: list<array{system: string, code: string, display: string}>}
     */
    public static function codeable(array $coding): array
    {
        return ['coding' => [$coding]];
    }
}
