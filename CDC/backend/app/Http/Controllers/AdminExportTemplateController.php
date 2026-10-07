<?php

namespace App\Http\Controllers;

use App\Models\ExportTemplate;
use App\Models\PlacementCycle;
use App\Services\AuditService;
use App\Support\ExportFieldCatalogue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Excel Templates (Superset parity S3): the admin's library of column layouts. Every write is audited.
 */
class AdminExportTemplateController extends Controller
{
    private const MAX_COLUMNS = 150;

    public function __construct(private readonly AuditService $audit)
    {
    }

    public function index(): JsonResponse
    {
        return response()->json([
            'templates' => ExportTemplate::query()->with('creator:id,name')->orderBy('name')->get()->map(fn (ExportTemplate $t) => $this->payload($t)),
        ]);
    }

    public function fields(): JsonResponse
    {
        return response()->json([
            'fields' => ExportFieldCatalogue::forPicker(),
            'cycles' => PlacementCycle::query()->orderByDesc('starts_on')->get(['id', 'name', 'status']),
            'types' => ExportTemplate::TYPES,
        ]);
    }

    public function show(ExportTemplate $exportTemplate): JsonResponse
    {
        return response()->json(['template' => $this->payload($exportTemplate->load('creator:id,name'))]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'type' => ['nullable', Rule::in(ExportTemplate::TYPES)],
        ], ['name.required' => 'Enter a template name.']);

        // A new template starts with one row, "Name", as in Superset.
        $template = ExportTemplate::create([
            'name' => trim($validated['name']),
            'type' => $validated['type'] ?? 'STUDENT_LIST',
            'columns' => [['key' => 'name', 'label' => 'Name']],
            'created_by' => $request->user()->id,
        ]);
        $this->audit->log($request, 'export_template.create', $template, null, $template->only(['name', 'type', 'columns']));

        return response()->json(['message' => 'Template created.', 'template' => $this->payload($template)], 201);
    }

    public function update(Request $request, ExportTemplate $exportTemplate): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'columns' => ['sometimes', 'array', 'max:'.self::MAX_COLUMNS],
            'columns.*.key' => ['required', 'string', 'max:60'],
            'columns.*.label' => ['nullable', 'string', 'max:150'],
            'columns.*.cycle_id' => ['nullable', 'integer', 'exists:placement_cycles,id'],
        ]);

        if (isset($validated['columns'])) {
            $validated['columns'] = $this->cleanColumns($validated['columns']);
        }
        if (isset($validated['name'])) {
            $validated['name'] = trim($validated['name']);
        }

        $before = $exportTemplate->only(array_keys($validated));
        $exportTemplate->update($validated);
        if ($before != $exportTemplate->only(array_keys($validated))) {
            $this->audit->log($request, 'export_template.update', $exportTemplate, $before, $exportTemplate->only(array_keys($validated)));
        }

        return response()->json(['message' => 'All changes saved', 'template' => $this->payload($exportTemplate)]);
    }

    public function duplicate(Request $request, ExportTemplate $exportTemplate): JsonResponse
    {
        $copy = ExportTemplate::create([
            'name' => mb_substr($exportTemplate->name.' (copy)', 0, 120),
            'type' => $exportTemplate->type,
            'columns' => $exportTemplate->columns,
            'created_by' => $request->user()->id,
        ]);
        $this->audit->log($request, 'export_template.duplicate', $copy, null, ['from' => $exportTemplate->id] + $copy->only(['name', 'type', 'columns']));

        return response()->json(['message' => 'Template duplicated.', 'template' => $this->payload($copy)], 201);
    }

    public function destroy(Request $request, ExportTemplate $exportTemplate): JsonResponse
    {
        $before = $exportTemplate->only(['id', 'name', 'type', 'columns']);
        $exportTemplate->delete();
        $this->audit->log($request, 'export_template.delete', null, $before, null);

        return response()->json(['message' => 'Template deleted.']);
    }

    /**
     * Known keys only; each key once (cycle fields once per cycle); labels trimmed (stored as typed — exports write
     * every header as an explicit string, so "=cmd" stays text).
     *
     * @param  list<array{key: string, label?: string|null, cycle_id?: int|null}>  $columns
     * @return list<array{key: string, label: string, cycle_id?: int}>
     */
    private function cleanColumns(array $columns): array
    {
        $fields = ExportFieldCatalogue::fields();
        $seen = [];
        $clean = [];
        foreach ($columns as $i => $column) {
            $key = $column['key'];
            if (! isset($fields[$key])) {
                throw ValidationException::withMessages(["columns.{$i}.key" => "Unknown field \"{$key}\"."]);
            }
            $isCycle = (bool) ($fields[$key]['cycle'] ?? false);
            if ($isCycle && empty($column['cycle_id'])) {
                throw ValidationException::withMessages(["columns.{$i}.cycle_id" => 'Choose a placement for this field.']);
            }
            $identity = $key.($isCycle ? ':'.$column['cycle_id'] : '');
            if (isset($seen[$identity])) {
                continue;
            }
            $seen[$identity] = true;
            $entry = ['key' => $key, 'label' => trim((string) ($column['label'] ?? '')) ?: $fields[$key]['label']];
            if ($isCycle) {
                $entry['cycle_id'] = (int) $column['cycle_id'];
            }
            $clean[] = $entry;
        }

        return $clean;
    }

    private function payload(ExportTemplate $template): array
    {
        return $template->only(['id', 'name', 'type', 'columns', 'created_at', 'updated_at']) + [
            'created_by_name' => $template->creator?->name,
        ];
    }
}
