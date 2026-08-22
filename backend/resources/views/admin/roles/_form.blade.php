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
                <div class="divide-y divide-gray-100">
                    <template x-for="group in groups" :key="group.key">
                        <div class="px-5 py-4">
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
                                <span class="text-xs rounded-full px-2 py-0.5 font-medium transition-colors"
                                      :class="{
                                          'bg-green-100 text-green-700': isGroupAllSelected(group),
                                          'bg-yellow-100 text-yellow-700': isGroupPartialSelected(group) && !isGroupAllSelected(group),
                                          'bg-gray-100 text-gray-500': !isGroupPartialSelected(group)
                                      }"
                                      x-text="groupSelectedCount(group) + ' / ' + group.permissions.length">
                                </span>
                            </div>
                            {{-- Individual permissions --}}
                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-x-4 gap-y-1.5 ml-6">
                                <template x-for="perm in group.permissions" :key="perm.name">
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input
                                            type="checkbox"
                                            name="permissions[]"
                                            :value="perm.name"
                                            x-model="selectedPermissions"
                                            :disabled="{{ $isSuperAdmin ? 'true' : 'false' }}"
                                            class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                        >
                                        <span class="text-xs text-gray-600" x-text="perm.label"></span>
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
