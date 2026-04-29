<?php

namespace App\Exports;

use App\Models\Quisioner\Choice;
use App\Models\Quisioner\ResponseDetail;
use App\Models\Siakad\Dosen;
use App\Models\Siakad\MataKuliah;
use App\Models\Siakad\Prodi;
use Generator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromGenerator;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ResponseDetailsExport implements FromGenerator, WithHeadings
{
    public function __construct(
        private readonly array $filters = [],
        private readonly int $chunkSize = 1000,
        private readonly bool $singleQuery = false
    ) {}

    public function headings(): array
    {
        return [
            'ResponID',
            'ProdiID',
            'NamaProdi',
            'DosenID',
            'NamaDosen',
            'MataKuliahID',
            'MKKode',
            'Nama Mata Kuliah',
            'Tahun Akademik',
            'Nama Category',
            'Aspect Text',
            'Answer Type',
            'Choice Value',
        ];
    }

    public function generator(): Generator
    {
        if ($this->singleQuery) {
            foreach ($this->buildQuery()->orderBy('rd.DetailID')->cursor() as $row) {
                $choiceValue = $this->normalizeChoiceValue(
                    $row->ChoiceValueRaw ?? null,
                    $row->ChoiceID ?? null,
                    $row->AnswerNumber ?? null
                );

                yield [
                    $row->ResponID,
                    null,
                    null,
                    $row->DosenID,
                    null,
                    $row->MatakuliahID,
                    null,
                    null,
                    $row->TahunAkademik,
                    $row->CategoryName,
                    $row->AspectText,
                    $row->AnswerType,
                    $choiceValue,
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
            $matakuliahIds = $rows->pluck('MatakuliahID')->filter()->unique()->values()->all();
            $choiceIds = $rows->pluck('ChoiceID')->filter()->unique()->values()->all();

            $dosenMap = Dosen::query()
                ->whereIn('Login', $dosenIds)
                ->pluck('Nama', 'Login');

            $matakuliahRows = MataKuliah::query()
                ->select(['MKID', 'MKKode', 'Nama', 'ProdiID'])
                ->whereIn('MKID', $matakuliahIds)
                ->get();

            $prodiIds = $matakuliahRows->pluck('ProdiID')->filter()->unique()->values()->all();
            $prodiMap = Prodi::query()
                ->whereIn('ProdiID', $prodiIds)
                ->pluck('Nama', 'ProdiID');

            $matakuliahMap = $matakuliahRows->keyBy('MKID');
            $choiceMap = Choice::query()
                ->whereIn('ChoiceID', $choiceIds)
                ->pluck('ChoiceValue', 'ChoiceID');

            foreach ($rows as $row) {
                $matakuliah = $matakuliahMap->get($row->MatakuliahID);
                $prodiId = optional($matakuliah)->ProdiID;
                $choiceValue = $this->normalizeChoiceValue(
                    $row->ChoiceValueRaw ?? null,
                    $row->ChoiceID ?? null,
                    $row->AnswerNumber ?? null,
                    $choiceMap
                );

                yield [
                    $row->ResponID,
                    $prodiId,
                    $prodiMap[$prodiId] ?? null,
                    $row->DosenID,
                    $dosenMap[$row->DosenID] ?? null,
                    $row->MatakuliahID,
                    optional($matakuliah)->MKKode,
                    optional($matakuliah)->Nama,
                    $row->TahunAkademik,
                    $row->CategoryName,
                    $row->AspectText,
                    $row->AnswerType,
                    $choiceValue,
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
            ->leftJoin('dk_tbl_category as cat', 'q.CategoryID', '=', 'cat.CategoryID')
            ->leftJoin('dk_tbl_choice as c', 'rd.ChoiceID', '=', 'c.ChoiceID')
            ->select([
                'rd.DetailID as DetailID',
                'rd.ResponID as ResponID',
                'rd.ChoiceID as ChoiceID',
                'rd.AnswerNumber as AnswerNumber',
                'r.TahunAkademik as TahunAkademik',
                'r.DosenID as DosenID',
                'r.MatakuliahID as MatakuliahID',
                'q.AspectText as AspectText',
                'q.AnswerType as AnswerType',
                'cat.CategoryName as CategoryName',
                'c.ChoiceValue as ChoiceValueRaw',
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
        if (!empty($this->filters['program_code'])) {
            $mkIds = MataKuliah::query()
                ->select(['MKID'])
                ->where('ProdiID', $this->filters['program_code'])
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

    private function normalizeChoiceValue(
        mixed $choiceValueRaw,
        mixed $choiceId,
        mixed $answerNumber,
        mixed $choiceMap = null
    ): mixed {
        if ($choiceValueRaw !== null && $choiceValueRaw !== '') {
            return $choiceValueRaw;
        }

        if ($choiceMap !== null && $choiceId !== null) {
            $mapped = null;

            if ($choiceMap instanceof Collection) {
                $mapped = $choiceMap->get($choiceId);
                if (($mapped === null || $mapped === '') && is_numeric($choiceId)) {
                    $mapped = $choiceMap->get((int) $choiceId);
                }
                if ($mapped === null || $mapped === '') {
                    $mapped = $choiceMap->get((string) $choiceId);
                }
            } elseif (is_array($choiceMap)) {
                $mapped = $choiceMap[$choiceId] ?? null;
            }

            if ($mapped !== null && $mapped !== '') {
                return $mapped;
            }
        }

        return $answerNumber;
    }
}
