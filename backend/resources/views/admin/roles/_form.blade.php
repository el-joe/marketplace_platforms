{{--
    Role form partial.
    Include with: @include('admin.roles._form', ['mode' => 'create'])
                  @include('admin.roles._form', ['mode' => 'edit'])

    Expected variables: $allPermissions (array of grouped perms from controller),
                        $role (Role|null)
--}}
@php
    $role   = $role ?? null;
    $isEdit = $mode === 'edit' && $role !== null;
    $isSuperAdmin = $isEdit && $role->name === 'super_admin';

    $selectedPermissions = old('permissions', $isEdit
        ? $role->permissions->pluck('name')->toArray()
        : []
    );
@endphp

<div class="space-y-6"
     x-data="{
        selectedPermissions: {{ json_encode($selectedPermissions) }},
        groups: {{ json_encode($allPermissions) }},
        query: '',

        normalize(value) {
            return String(value || '')
                .toLowerCase()
                .replace(/[\u064B-\u0652\u0640]/g, '')
                .replace(/[أإآ]/g, 'ا')
                .replace(/ى/g, 'ي')
                .replace(/ة/g, 'ه')
                .replace(/[_.\-]+/g, ' ')
                .trim();
        },
        groupLabelMatches(group) {
            const q = this.normalize(this.query);
            return q === '' || [group.key, group.label, group.label_en, group.label_ar].some(v => this.normalize(v).includes(q));
        },
        permissionVisible(group, perm) {
            const q = this.normalize(this.query);
            return q === '' || this.groupLabelMatches(group) || this.normalize(perm.search_text).includes(q);
        },
        groupVisible(group) {
            return group.permissions.some(p => this.permissionVisible(group, p));
        },
        get visibleGroupCount() {
            return this.groups.filter(g => this.groupVisible(g)).length;
        },
        selectGroup(group, selected) {
            group.permissions.forEach(p => {
                const idx = this.selectedPermissions.indexOf(p.name);
                if (selected && idx === -1) this.selectedPermissions.push(p.name);
                if (!selected && idx > -1) this.selectedPermissions.splice(idx, 1);
            });
        },

        isGroupAllSelected(group) {
            return group.permissions.length > 0
                && group.permissions.every(p => this.selectedPermissions.includes(p.name));
        },
        isGroupPartialSelected(group) {
            return group.permissions.some(p => this.selectedPermissions.includes(p.name))
                && !this.isGroupAllSelected(group);
        },
        groupSelectedCount(group) {
            return group.permissions.filter(p => this.selectedPermissions.includes(p.name)).length;
        },
        toggleGroup(group) {
            if (this.isGroupAllSelected(group)) {
                group.permissions.forEach(p => {
                    const idx = this.selectedPermissions.indexOf(p.name);
                    if (idx > -1) this.selectedPermissions.splice(idx, 1);
                });
            } else {
                group.permissions.forEach(p => {
                    if (!this.selectedPermissions.includes(p.name)) {
                        this.selectedPermissions.push(p.name);
                    }
                });
            }
        }
     }">

    {{-- ── Page Header ─────────────────────────────────────────────────── --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-semibold text-gray-900">
                {{ $isEdit ? __('admin.roles_section.edit_role_title', ['name' => $role->name]) : __('admin.roles_section.new_role') }}
            </h1>
            @if($isEdit)
                <p class="text-sm text-gray-500 mt-1">{{ __('admin.roles_section.guard_label', ['guard' => $role->guard_name]) }}</p>
            @endif
        </div>
        <div class="flex gap-2">
            <a href="{{ route('admin.roles.index') }}" class="btn btn-secondary">{{ __('admin.roles_section.cancel') }}</a>
            @if(!$isSuperAdmin)
                <button type="submit" class="btn btn-primary" id="save-btn">
                    {{ $isEdit ? __('admin.roles_section.save_changes') : __('admin.roles_section.create_role') }}
                </button>
            @else
                <span class="btn btn-secondary opacity-50 cursor-not-allowed" title="{{ __('admin.roles_section.super_admin_readonly_title') }}">{{ __('admin.roles_section.save_changes') }}</span>
            @endif
        </div>
    </div>

    @if($isSuperAdmin)
        <div class="border border-amber-300 bg-amber-50 rounded-lg px-4 py-3 text-sm text-amber-800">
            {{ __('admin.roles_section.super_admin_notice') }}
        </div>
    @endif

    <div class="flex flex-col lg:flex-row gap-6 items-start">

        {{-- ═══════ LEFT: Role Details + Permission Matrix ═══════ --}}
        <div class="flex-1 min-w-0 space-y-4">

            {{-- Role Name --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h2 class="text-sm font-semibold text-gray-900">{{ __('admin.roles_section.role_details') }}</h2>
                </div>
                <div class="px-5 py-5">
                    <x-form-input
                        name="name"
                        label="{{ __('admin.roles_section.role_name') }}"
                        type="text"
                        :value="old('name', $isEdit ? $role->name : '')"
                        placeholder="{{ __('admin.roles_section.role_name_placeholder') }}"
                        helpText="{{ __('admin.roles_section.role_name_help') }}"
                        :disabled="$isSuperAdmin"
                        required
                    />
                </div>
            </div>

            {{-- Permission Matrix --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
                <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-gray-900">{{ __('admin.roles_section.permissions') }}</h2>
                    <span class="text-xs text-gray-500 bg-gray-100 rounded-full px-2 py-0.5"
                          x-text="{{ json_encode(__('admin.roles_section.selected_count')) }}.replace(':count', selectedPermissions.length)">
                    </span>
                </div>
                <div class="px-5 py-3 border-b border-gray-100">
                    <label for="permission-search" class="sr-only">{{ __('permissions.search_placeholder') }}</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3 text-gray-400">
                            <x-heroicon name="magnifying-glass" class="w-4 h-4" />
                        </span>
                        <input id="permission-search" type="search" x-model.debounce.150ms="query" autocomplete="off"
                               @keydown.escape.prevent="query = ''"
                               placeholder="{{ __('permissions.search_placeholder') }}"
                               class="input w-full ps-9">
                    </div>
                </div>
                <p x-show="query !== '' && visibleGroupCount === 0" x-cloak class="px-5 py-6 text-sm text-gray-500 text-center">
                    {{ __('permissions.no_results') }}
                </p>
                <div class="divide-y divide-gray-100">
                    <template x-for="group in groups" :key="group.key">
                        <div class="px-5 py-4" x-show="groupVisible(group)">
                            {{-- Group header --}}
                            <div class="flex items-center justify-between mb-3 cursor-pointer group"
                                 @click="!{{ $isSuperAdmin ? 'true' : 'false' }} && toggleGroup(group)">
                                <div class="flex items-center gap-2">
                                    <input
                                        type="checkbox"
                                        class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                        :checked="isGroupAllSelected(group)"
                                        :indeterminate="isGroupPartialSelected(group)"
                                        @click.stop="!{{ $isSuperAdmin ? 'true' : 'false' }} && toggleGroup(group)"
                                        :disabled="{{ $isSuperAdmin ? 'true' : 'false' }}"
                                    >
                                    <span class="text-sm font-medium text-gray-800" x-text="group.label"></span>
                                </div>
                                <div class="flex items-center gap-2">
                                @if(!$isSuperAdmin)
                                    <button type="button" class="text-xs text-indigo-600 hover:underline"
                                            x-show="!isGroupAllSelected(group)"
                                            @click.stop="selectGroup(group, true)">{{ __('permissions.select_all_in_group') }}</button>
                                    <button type="button" class="text-xs text-gray-500 hover:underline"
                                            x-show="isGroupPartialSelected(group) || isGroupAllSelected(group)"
                                            @click.stop="selectGroup(group, false)">{{ __('permissions.clear_group') }}</button>
                                @endif
                                <span class="text-xs rounded-full px-2 py-0.5 font-medium transition-colors"
                                      :class="{
                                          'bg-green-100 text-green-700': isGroupAllSelected(group),
                                          'bg-yellow-100 text-yellow-700': isGroupPartialSelected(group) && !isGroupAllSelected(group),
                                          'bg-gray-100 text-gray-500': !isGroupPartialSelected(group)
                                      }"
                                      x-text="groupSelectedCount(group) + ' / ' + group.permissions.length">
                                </span>
                                </div>
                            </div>
                            <p x-show="group.description" x-text="group.description" class="text-xs text-gray-500 mb-2 ms-6"></p>
                            {{-- Individual permissions --}}
                            <div class="grid gap-x-4 gap-y-1.5 ms-6"
                                 :class="group.key === 'portal_content' ? 'grid-cols-1 sm:grid-cols-2' : 'grid-cols-2 sm:grid-cols-3'">
                                <template x-for="perm in group.permissions" :key="perm.name">
                                    <label class="flex items-start gap-2 cursor-pointer" :data-permission="perm.name" x-show="permissionVisible(group, perm)">
                                        <input
                                            type="checkbox"
                                            name="permissions[]"
                                            :value="perm.name"
                                            x-model="selectedPermissions"
                                            :disabled="{{ $isSuperAdmin ? 'true' : 'false' }}"
                                            class="mt-0.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                        >
                                        <span>
                                            <span class="text-xs text-gray-600" x-text="perm.label"></span>
                                            <span x-show="perm.description" x-text="perm.description" class="block text-[11px] leading-snug text-gray-400"></span>
                                        </span>
                                    </label>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

        </div>

        {{-- ═══════ RIGHT: Summary ═══════ --}}
        <div class="w-full lg:w-64 shrink-0">
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm sticky top-4">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h2 class="text-sm font-semibold text-gray-900">{{ __('admin.roles_section.summary') }}</h2>
                </div>
                <div class="px-5 py-4 space-y-3 text-sm text-gray-600">
                    <div class="flex justify-between">
                        <span>{{ __('admin.roles_section.guard') }}</span>
                        <code class="text-xs font-mono text-gray-500">admin</code>
                    </div>
                    <div class="flex justify-between">
                        <span>{{ __('admin.roles_section.permissions') }}</span>
                        <span class="font-semibold text-gray-900" x-text="selectedPermissions.length"></span>
                    </div>
                    <div class="flex justify-between">
                        <span>{{ __('admin.roles_section.groups') }}</span>
                        <span class="font-semibold text-gray-900"
                              x-text="groups.filter(g => g.permissions.some(p => selectedPermissions.includes(p.name))).length"></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
