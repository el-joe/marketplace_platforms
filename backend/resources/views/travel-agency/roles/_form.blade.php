{{--
    Role form partial for the Travel Agency panel.
    Include with: @include('travel-agency.roles._form', ['mode' => 'create'])
                  @include('travel-agency.roles._form', ['mode' => 'edit'])

    Expected variables: $allPermissions (array of grouped perms from controller), $role (Role|null)
--}}
@php
    $role = $role ?? null;
    $isEdit = $mode === 'edit' && $role !== null;
    $selectedPermissions = old('permissions', $isEdit ? $role->permissions->pluck('name')->toArray() : []);
@endphp

<div class="px-4 py-6 sm:px-6 lg:px-8"
    x-data="{
        selectedPermissions: {{ json_encode($selectedPermissions) }},
        groups: {{ json_encode($allPermissions) }},
        isGroupAllSelected(group) {
            return group.permissions.length > 0 && group.permissions.every(p => this.selectedPermissions.includes(p.name));
        },
        isGroupPartialSelected(group) {
            return group.permissions.some(p => this.selectedPermissions.includes(p.name)) && !this.isGroupAllSelected(group);
        },
        toggleGroup(group) {
            if (this.isGroupAllSelected(group)) {
                group.permissions.forEach(p => {
                    const idx = this.selectedPermissions.indexOf(p.name);
                    if (idx > -1) this.selectedPermissions.splice(idx, 1);
                });
            } else {
                group.permissions.forEach(p => {
                    if (!this.selectedPermissions.includes(p.name)) this.selectedPermissions.push(p.name);
                });
            }
        }
    }">

    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">
                {{ $isEdit ? __('travel.roles.edit_role_title', ['name' => $role->label ?? $role->name]) : __('travel.roles.new_role') }}
            </h1>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('travel-agency.roles.index') }}"
                class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                {{ __('common.cancel') }}
            </a>
            <button type="submit" id="save-btn"
                class="rounded-lg bg-primary-600 px-5 py-2 text-sm font-semibold text-white hover:bg-primary-700 transition-colors disabled:opacity-60 disabled:cursor-not-allowed">
                {{ $isEdit ? __('travel.roles.save_changes') : __('travel.roles.create_role') }}
            </button>
        </div>
    </div>

    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm mb-4">
        <div class="px-5 py-4 border-b border-gray-100">
            <h2 class="text-sm font-semibold text-gray-900">{{ __('travel.roles.role_details') }}</h2>
        </div>
        <div class="px-5 py-5">
            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('travel.roles.role_name') }} <span class="text-red-500">*</span></label>
            <input type="text" name="name" autocomplete="off" value="{{ old('name', $isEdit ? ($role->label ?? $role->name) : '') }}"
                pattern="[a-z0-9_]+" placeholder="{{ __('travel.roles.role_name_placeholder') }}"
                class="block w-full max-w-sm rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500">
            <p class="mt-1 text-xs text-gray-500">{{ __('travel.roles.role_name_help') }}</p>
        </div>
    </div>

    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-gray-900">{{ __('travel.roles.permissions') }}</h2>
            <span class="text-xs text-gray-500 bg-gray-100 rounded-full px-2 py-0.5" x-text="selectedPermissions.length + ' {{ __('travel.roles.selected') }}'"></span>
        </div>
        <div class="divide-y divide-gray-100">
            <template x-for="group in groups" :key="group.key">
                <div class="px-5 py-4">
                    <div class="flex items-center justify-between mb-3 cursor-pointer" @click="toggleGroup(group)">
                        <div class="flex items-center gap-2">
                            <input type="checkbox" class="rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                                :checked="isGroupAllSelected(group)" :indeterminate="isGroupPartialSelected(group)" @click.stop="toggleGroup(group)">
                            <span class="text-sm font-medium text-gray-800" x-text="group.label"></span>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-x-4 gap-y-1.5 ms-6">
                        <template x-for="perm in group.permissions" :key="perm.name">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" name="permissions[]" :value="perm.name" x-model="selectedPermissions"
                                    class="rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                                <span class="text-xs text-gray-600" x-text="perm.label"></span>
                            </label>
                        </template>
                    </div>
                </div>
            </template>
        </div>
    </div>

    <div id="role-form-error" class="hidden mt-4 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700"></div>
</div>
