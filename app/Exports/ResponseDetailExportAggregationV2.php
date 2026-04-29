<?php

namespace App\Exports;

use App\Models\Siakad\Dosen;
use App\Models\Siakad\MataKuliah;
use App\Models\Siakad\Prodi;
use Generator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromGenerator;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ResponseDetailExportAggregationV2 implements FromGenerator, WithHeadings
{
    public function __construct(
        private readonly array $filters = []
    ) {
    }

    public function headings(): array
    {
        return [
            'No',
            'Nama Prodi',
            'Dosen ID',
            'Nama Dosen',
            'MK Kode',
            'Nama MK',
            'Tahun Akademik',
            'Type Pertanyaan',
            'Pertanyaan',
            'Rata-rata',
        ];
    }

    public function generator(): Generator
    {
        $matakuliahIds = $this->resolveMatakuliahIdsFromFilters();
        if ($matakuliahIds !== null && empty($matakuliahIds)) {
            return;
        }

        $idQuery = $this->buildIdQuery($matakuliahIds);

        $dosenIds = (clone $idQuery)
            ->distinct()
            ->pluck('r.DosenID')
            ->filter()
            ->values()
            ->all();

        $mkIds = (clone $idQuery)
            ->distinct()
            ->pluck('r.MatakuliahID')
            ->filter()
            ->values()
            ->all();

        if (empty($dosenIds) || empty($mkIds)) {
            return;
        }

        $dosenMap = Dosen::query()
            ->whereIn('Login', $dosenIds)
            ->pluck('Nama', 'Login');

        $mkRows = MataKuliah::query()
            ->select(['MKID', 'MKKode', 'Nama', 'ProdiID'])
            ->whereIn('MKID', $mkIds)
            ->get();

        $mkMap = $mkRows->keyBy('MKID');

        $prodiIds = $mkRows->pluck('ProdiID')->filter()->unique()->values()->all();
        $prodiMap = Prodi::query()
            ->whereIn('ProdiID', $prodiIds)
            ->pluck('Nama', 'ProdiID');

        $rows = $this->buildAggregateQuery($matakuliahIds)->cursor();

        $no = 1;
        foreach ($rows as $row) {
            $mk = $mkMap->get($row->MatakuliahID) ?? $mkMap->get((string) $row->MatakuliahID);
            $prodiId = $mk?->ProdiID;

            yield [
                $no,
                $prodiMap[$prodiId] ?? '-',
                $row->DosenID,
                $dosenMap[$row->DosenID] ?? '-',
                $mk?->MKKode ?? '-',
                $mk?->Nama ?? '-',
                $row->TahunAkademik ?? '-',
                $row->CategoryName ?? '-',
                $row->AspectText ?? '-',
                round((float) ($row->ChoiceValueAvg ?? 0), 2),
            ];

            $no++;
        }
    }

    private function buildIdQuery(?array $matakuliahIds): Builder
    {
        $query = DB::connection('quisioner')
            ->table('dk_tbl_response_detail as rd')
            ->join('dk_tbl_response as r', 'r.ResponID', '=', 'rd.ResponID')
            ->leftJoin('dk_tbl_question as q', 'q.AspectID', '=', 'rd.AspectID')
            ->where('q.AnswerType', 'CHOICE')
            ->select([
                'r.DosenID',
                'r.MatakuliahID',
            ]);

        $this->applyCommonFilters($query, $matakuliahIds);

        return $query;
    }

    private function buildAggregateQuery(?array $matakuliahIds): Builder
    {
        $query = DB::connection('quisioner')
            ->table('dk_tbl_response_detail as rd')
            ->join('dk_tbl_response as r', 'r.ResponID', '=', 'rd.ResponID')
            ->leftJoin('dk_tbl_question as q', 'q.AspectID', '=', 'rd.AspectID')
            ->leftJoin('dk_tbl_choice as c', 'c.ChoiceID', '=', 'rd.ChoiceID')
            ->leftJoin('dk_tbl_category as cat', 'cat.CategoryID', '=', 'q.CategoryID')
            ->where('q.AnswerType', 'CHOICE')
            ->select([
                'r.TahunAkademik',
                'r.DosenID',
                'r.MatakuliahID',
                'q.AspectText',
                'cat.CategoryName',
                DB::raw('AVG(COALESCE(c.ChoiceValue, rd.AnswerNumber, 0)) as ChoiceValueAvg'),
            ])
            ->groupBy(
                'r.TahunAkademik',
                'r.DosenID',
                'r.MatakuliahID',
                'q.AspectText',
                'cat.CategoryName'
            )
            ->orderBy('r.DosenID')
            ->orderBy('r.MatakuliahID')
            ->orderBy('q.AspectText');

        $this->applyCommonFilters($query, $matakuliahIds);

        return $query;
    }

    private function applyCommonFilters(Builder $query, ?array $matakuliahIds): void
    {
        if (!empty($this->filters['response_id'])) {
            $query->where('rd.ResponID', $this->filters['response_id']);
        }

        if (!empty($this->filters['aspect_id'])) {
            $query->where('rd.AspectID', $this->filters['aspect_id']);
        }

        if (!empty($this->filters['choice_id'])) {
            $query->where('rd.ChoiceID', $this->filters['choice_id']);
        }

        $tahunAkademik = $this->filters['tahun_akademik'] ?? $this->filters['tahun_id'] ?? null;
        if (!empty($tahunAkademik)) {
            $query->where('r.TahunAkademik', $tahunAkademik);
        }

        if ($matakuliahIds !== null) {
            $query->whereIn('r.MatakuliahID', $matakuliahIds);
        }
    }

    private function resolveMatakuliahIdsFromFilters(): ?array
    {
        $namaProdi = $this->filters['nama_prodi'] ?? $this->filters['prodi'] ?? null;

        $resultIds = null;

        if (!empty($this->filters['matakuliah_id'])) {
            $resultIds = [(string) $this->filters['matakuliah_id']];
        }

        if (!empty($this->filters['nama_matakuliah'])) {
            $ids = MataKuliah::query()
                ->where('Nama', 'like', '%' . $this->filters['nama_matakuliah'] . '%')
                ->pluck('MKID')
                ->map(fn($value) => (string) $value)
                ->all();

            $resultIds = $this->intersectIds($resultIds, $ids);
        }

        if (!empty($this->filters['prodi_id'])) {
            $ids = MataKuliah::query()
                ->where('ProdiID', $this->filters['prodi_id'])
                ->pluck('MKID')
                ->map(fn($value) => (string) $value)
                ->all();

            $resultIds = $this->intersectIds($resultIds, $ids);
        }

        if (!empty($this->filters['program_code'])) {
            $ids = MataKuliah::query()
                ->where('ProdiID', $this->filters['program_code'])
                ->pluck('MKID')
                ->map(fn($value) => (string) $value)
                ->all();

            $resultIds = $this->intersectIds($resultIds, $ids);
        }

        if (empty($this->filters['prodi_id']) && !empty($namaProdi)) {
            $ids = MataKuliah::query()
                ->whereHas('prodi', function ($query) use ($namaProdi) {
                    $query->where('Nama', 'like', '%' . $namaProdi . '%');
                })
                ->pluck('MKID')
                ->map(fn($value) => (string) $value)
                ->all();

            $resultIds = $this->intersectIds($resultIds, $ids);
        }

        return $resultIds;
    }

    private function intersectIds(?array $existingIds, array $incomingIds): array
    {
        $incomingIds = array_values(array_unique($incomingIds));

        if ($existingIds === null) {
            return $incomingIds;
        }

        return array_values(array_intersect($existingIds, $incomingIds));
    }
}
