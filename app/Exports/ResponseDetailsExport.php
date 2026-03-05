<?php

namespace App\Exports;

use App\Models\Quisioner\ResponseDetail;
use App\Models\Siakad\Dosen;
use App\Models\Siakad\Mahasiswa;
use App\Models\Siakad\MataKuliah;
use App\Models\Siakad\Prodi;
use Generator;
use Maatwebsite\Excel\Concerns\FromGenerator;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ResponseDetailsExport implements FromGenerator, WithHeadings
{
    public function __construct(
        private readonly array $filters = [],
        private readonly int $chunkSize = 1000,
        private readonly bool $singleQuery = false
    ) {
    }

    public function headings(): array
    {
        return [
            'DetailID',
            'ResponID',
            'AspectID',
            'ChoiceID',
            'AnswerText',
            'AnswerNumber',
            'TahunAkademik',
            'Semester',
            'DosenLogin',
            'DosenNama',
            'MahasiswaID',
            'MahasiswaNama',
            'MatakuliahID',
            'MatakuliahNama',
            'ProdiID',
            'ProdiNama',
            'AspectText',
            'ChoiceLabel',
            'ChoiceValue',
        ];
    }

    public function generator(): Generator
    {
        if ($this->singleQuery) {
            foreach ($this->buildQuery()->orderBy('rd.DetailID')->cursor() as $row) {
                yield [
                    $row->DetailID,
                    $row->ResponID,
                    $row->AspectID,
                    $row->ChoiceID,
                    $row->AnswerText,
                    $row->AnswerNumber,
                    $row->TahunAkademik,
                    $row->Semester,
                    $row->DosenID,
                    null,
                    $row->MahasiswaID,
                    null,
                    $row->MatakuliahID,
                    null,
                    null,
                    null,
                    $row->AspectText,
                    $row->ChoiceLabel,
                    $row->ChoiceValue,
                ];
            }

            return;
        }

        $lastId = 0;

        while (true) {
            $rows = $this->buildQuery()
                ->where('rd.DetailID', '>', $lastId)
                ->orderBy('rd.DetailID')
                ->limit($this->chunkSize)
                ->get();

            if ($rows->isEmpty()) {
                break;
            }

            $dosenIds = $rows->pluck('DosenID')->filter()->unique()->values()->all();
            $mahasiswaIds = $rows->pluck('MahasiswaID')->filter()->unique()->values()->all();
            $matakuliahIds = $rows->pluck('MatakuliahID')->filter()->unique()->values()->all();

            $dosenMap = Dosen::query()
                ->whereIn('Login', $dosenIds)
                ->pluck('Nama', 'Login');

            $mahasiswaMap = Mahasiswa::query()
                ->whereIn('MhswID', $mahasiswaIds)
                ->pluck('Nama', 'MhswID');

            $matakuliahRows = MataKuliah::query()
                ->select(['MKID', 'Nama', 'ProdiID'])
                ->whereIn('MKID', $matakuliahIds)
                ->get();

            $prodiIds = $matakuliahRows->pluck('ProdiID')->filter()->unique()->values()->all();
            $prodiMap = Prodi::query()
                ->whereIn('ProdiID', $prodiIds)
                ->pluck('Nama', 'ProdiID');

            $matakuliahMap = $matakuliahRows->keyBy('MKID');

            foreach ($rows as $row) {
                $matakuliah = $matakuliahMap->get($row->MatakuliahID);
                $prodiId = optional($matakuliah)->ProdiID;

                yield [
                    $row->DetailID,
                    $row->ResponID,
                    $row->AspectID,
                    $row->ChoiceID,
                    $row->AnswerText,
                    $row->AnswerNumber,
                    $row->TahunAkademik,
                    $row->Semester,
                    $row->DosenID,
                    $dosenMap[$row->DosenID] ?? null,
                    $row->MahasiswaID,
                    $mahasiswaMap[$row->MahasiswaID] ?? null,
                    $row->MatakuliahID,
                    optional($matakuliah)->Nama,
                    $prodiId,
                    $prodiMap[$prodiId] ?? null,
                    $row->AspectText,
                    $row->ChoiceLabel,
                    $row->ChoiceValue,
                ];
            }

            $lastId = (int) $rows->last()->DetailID;
        }
    }

    private function buildQuery()
    {
        $query = ResponseDetail::query()
            ->from('dk_tbl_response_detail as rd')
            ->join('dk_tbl_response as r', 'rd.ResponID', '=', 'r.ResponID')
            ->leftJoin('dk_tbl_question as q', 'rd.AspectID', '=', 'q.AspectID')
            ->leftJoin('dk_tbl_choice as c', 'rd.ChoiceID', '=', 'c.ChoiceID')
            ->select([
                'rd.DetailID as DetailID',
                'rd.ResponID as ResponID',
                'rd.AspectID as AspectID',
                'rd.ChoiceID as ChoiceID',
                'rd.AnswerText as AnswerText',
                'rd.AnswerNumber as AnswerNumber',
                'r.TahunAkademik as TahunAkademik',
                'r.Semester as Semester',
                'r.DosenID as DosenID',
                'r.MahasiswaID as MahasiswaID',
                'r.MatakuliahID as MatakuliahID',
                'q.AspectText as AspectText',
                'c.ChoiceLabel as ChoiceLabel',
                'c.ChoiceValue as ChoiceValue',
            ]);

        if (!empty($this->filters['response_id'])) {
            $query->where('rd.ResponID', $this->filters['response_id']);
        }

        if (!empty($this->filters['aspect_id'])) {
            $query->where('rd.AspectID', $this->filters['aspect_id']);
        }

        if (!empty($this->filters['choice_id'])) {
            $query->where('rd.ChoiceID', $this->filters['choice_id']);
        }

        if (!empty($this->filters['tahun_akademik'])) {
            $query->where('r.TahunAkademik', $this->filters['tahun_akademik']);
        }

        if (!empty($this->filters['nama_prodi'])) {
            $mkIds = MataKuliah::query()
                ->select(['MKID'])
                ->whereHas('prodi', function ($subQuery) {
                    $subQuery->where('Nama', 'like', '%' . $this->filters['nama_prodi'] . '%');
                })
                ->pluck('MKID');

            if ($mkIds->isEmpty()) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereIn('r.MatakuliahID', $mkIds);
            }
        }

        if (!empty($this->filters['matakuliah_id'])) {
            $query->where('r.MatakuliahID', $this->filters['matakuliah_id']);
        }

        if (!empty($this->filters['nama_matakuliah'])) {
            $mkIds = MataKuliah::query()
                ->select(['MKID'])
                ->where('Nama', 'like', '%' . $this->filters['nama_matakuliah'] . '%')
                ->pluck('MKID');

            if ($mkIds->isEmpty()) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereIn('r.MatakuliahID', $mkIds);
            }
        }

        return $query;
    }
}
