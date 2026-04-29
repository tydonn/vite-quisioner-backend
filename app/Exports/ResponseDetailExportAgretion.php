<?php

namespace App\Exports;

use App\Models\Siakad\Dosen;
use App\Models\Siakad\MataKuliah;
use App\Models\Siakad\Prodi;
use Generator;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromGenerator;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ResponseDetailExportAgretion implements FromGenerator, WithHeadings
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
        $tahunId = $this->filters['tahun_akademik'] ?? $this->filters['tahun_id'] ?? null;
        $prodiId = $this->filters['prodi_id'] ?? $this->filters['program_code'] ?? null;

        if (empty($prodiId) && !empty($this->filters['nama_prodi'])) {
            $prodiId = Prodi::query()
                ->where('Nama', 'like', '%' . $this->filters['nama_prodi'] . '%')
                ->value('ProdiID');
        }

        if (empty($tahunId) || empty($prodiId)) {
            return;
        }

        $namaProdi = Prodi::query()
            ->where('ProdiID', $prodiId)
            ->value('Nama') ?? '-';

        $jadwalRows = DB::connection('siakad')
            ->table('jadwal as a')
            ->select(['a.TahunID', 'a.DosenID', 'a.MKID'])
            ->where('a.NA', 'N')
            ->where('a.TahunID', $tahunId)
            ->where('a.ProdiID', $prodiId)
            ->groupBy('a.TahunID', 'a.DosenID', 'a.MKID')
            ->get();

        if ($jadwalRows->isEmpty()) {
            return;
        }

        $dosenIds = $jadwalRows->pluck('DosenID')->filter()->unique()->values()->all();
        $mkIds = $jadwalRows->pluck('MKID')->filter()->unique()->values()->all();

        if (empty($dosenIds) || empty($mkIds)) {
            return;
        }

        $dosenMap = Dosen::query()
            ->whereIn('Login', $dosenIds)
            ->pluck('Nama', 'Login');

        $mkMap = MataKuliah::query()
            ->select(['MKID', 'MKKode', 'Nama'])
            ->whereIn('MKID', $mkIds)
            ->get()
            ->keyBy('MKID');

        $rows = DB::connection('quisioner')
            ->table('dk_tbl_response_detail as a')
            ->join('dk_tbl_response as b', 'b.ResponID', '=', 'a.ResponID')
            ->leftJoin('dk_tbl_question as c', 'c.AspectID', '=', 'a.AspectID')
            ->leftJoin('dk_tbl_choice as d', 'd.ChoiceID', '=', 'a.ChoiceID')
            ->leftJoin('dk_tbl_category as e', 'e.CategoryID', '=', 'c.CategoryID')
            ->select([
                'b.TahunAkademik',
                'b.DosenID',
                'b.MatakuliahID',
                'c.AspectText',
                'e.CategoryName',
                DB::raw('AVG(d.ChoiceValue) as ChoiceValueAvg'),
            ])
            ->whereIn('b.DosenID', $dosenIds)
            ->whereIn('b.MatakuliahID', $mkIds)
            ->where('c.AnswerType', 'CHOICE')
            ->where('b.TahunAkademik', $tahunId)
            ->whereRaw('SUBSTRING(b.MahasiswaID, 3) LIKE ?', [$prodiId . '%'])
            ->groupBy(
                'b.TahunAkademik',
                'b.DosenID',
                'b.MatakuliahID',
                'c.AspectText',
                'e.CategoryName'
            )
            ->orderBy('b.DosenID')
            ->orderBy('b.MatakuliahID')
            ->orderBy('c.AspectText')
            ->cursor();

        $no = 1;
        foreach ($rows as $row) {
            $mk = $mkMap->get($row->MatakuliahID);

            yield [
                $no,
                $namaProdi,
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
}
