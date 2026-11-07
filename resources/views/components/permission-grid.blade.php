@props(['crud', 'workflow', 'checked' => [], 'name' => 'permissions[]', 'disabled' => false])
@php $actionLabels = config('roles.actions'); @endphp
<div {{ $attributes->merge(['class' => 'perm-grid']) }}>
    <div class="mb-2 flex items-center justify-between">
        <span class="text-sm font-bold">Master data (View / Create / Edit / Delete)</span>
        <span class="flex gap-3 text-xs font-bold text-brand">
            <button type="button" class="perm-all hover:underline">Select all</button>
            <button type="button" class="perm-none hover:underline">Clear</button>
        </span>
    </div>
    <div class="overflow-x-auto rounded-xl border border-line">
        <table class="w-full min-w-[460px] text-sm">
            <thead class="bg-slate-50 text-xs font-bold text-muted">
                <tr>
                    <th class="px-4 py-2 text-left">Module</th>
                    @foreach ($actionLabels as $label)<th class="px-2 py-2 text-center">{{ $label }}</th>@endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($crud as $module => $def)
                    <tr class="border-t border-slate-100">
                        <td class="px-4 py-2.5">
                            <div class="font-bold">{{ $def['label'] }}</div>
                            <div class="text-xs text-muted">{{ $def['description'] }}</div>
                        </td>
                        @foreach ($actionLabels as $action => $label)
                            <td class="px-2 py-2.5 text-center">
                                @isset($def['perms'][$action])
                                    <input type="checkbox" name="{{ $name }}" value="{{ $def['perms'][$action] }}" class="perm-check h-4 w-4 accent-brand"
                                           aria-label="{{ $def['label'] }} - {{ $label }}" @checked(in_array($def['perms'][$action], $checked, true)) @disabled($disabled)>
                                @else
                                    <span class="text-slate-300">-</span>
                                @endisset
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mb-2 mt-5 text-sm font-bold">Workflow modules</div>
    <div class="grid gap-2 sm:grid-cols-2">
        @foreach ($workflow as $key => $mod)
            <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-line p-3 transition-colors hover:border-brand/40 has-[:checked]:border-brand has-[:checked]:bg-brand-tint/40">
                <input type="checkbox" name="{{ $name }}" value="{{ $key }}" class="perm-check mt-0.5 h-4 w-4 accent-brand" @checked(in_array($key, $checked, true)) @disabled($disabled)>
                <span>
                    <span class="block text-sm font-bold">{{ $mod['label'] }}</span>
                    <span class="block text-xs text-muted">{{ $mod['description'] ?? '' }}</span>
                </span>
            </label>
        @endforeach
    </div>
</div>
@once
@push('scripts')
<script>
    document.querySelectorAll('.perm-grid').forEach((g) => {
        const boxes = () => [...g.querySelectorAll('.perm-check:not(:disabled)')];
        g.querySelector('.perm-all')?.addEventListener('click', () => boxes().forEach((c) => c.checked = true));
        g.querySelector('.perm-none')?.addEventListener('click', () => boxes().forEach((c) => c.checked = false));
    });
</script>
@endpush
@endonce
