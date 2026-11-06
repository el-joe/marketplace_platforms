{{--
    Admin form partial.
    Include with: @include('admin.admins._form', ['mode' => 'create'])
                  @include('admin.admins._form', ['mode' => 'edit'])

    Expected variables: $allRoles (Role collection with permissions loaded),
                        $countries (Country collection), $model (Admin|null)
--}}
@php
    $model  = $model ?? null;
    $isEdit = $mode === 'edit' && $model !== null;

    $val = fn($field, $default = '') => old($field, $isEdit ? ($model->{$field} ?? $default) : $default);

    // Build role→permissions map for Alpine.js
    $rolePermissions = [];
    foreach ($allRoles as $role) {
        $rolePermissions[$role->name] = $role->permissions->pluck('name')->sort()->values()->toArray();
    }

    $currentRoles = old('roles', $isEdit ? $model->getRoleNames()->toArray() : []);
    $directPortal = old('portal_permissions', $directPortalPermissions ?? []);
    $portalPermissionNames = \App\Support\PortalPermissions::directlyAssignable();

    $statusOptions = ['active' => __('common.active'), 'inactive' => __('common.inactive')];
    $countryOptions = ['' => '— ' . __('admin.admins_section.no_country') . ' —'] + $countries->pluck('name_en', 'id')->toArray();
@endphp

<div class="space-y-6"
     x-data="{
        selectedRoles: {{ json_encode($currentRoles) }},
        rolePermissions: {{ json_encode($rolePermissions) }},
        directPortal: {{ json_encode(array_values($directPortal)) }},
        viaRole(perm) {
            return this.selectedRoles.some(r => (this.rolePermissions[r] || []).includes(perm));
        },
        get effectivePermissions() {
            const all = new Set(this.directPortal);
            this.selectedRoles.forEach(r => {
                (this.rolePermissions[r] || []).forEach(p => all.add(p));
            });
            return [...all].sort();
        }
     }">

    {{-- ── Page Header ─────────────────────────────────────────────────── --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-semibold text-gray-900">
                {{ $isEdit ? __('admin.admins_section.edit_admin') . ': ' . e($model->name) : __('admin.admins_section.new_admin') }}
            </h1>
            @if($isEdit)
                <p class="text-sm text-gray-500 mt-1">
                    {{ __('admin.admins_section.member_since') }} {{ admin_tz($model->created_at)?->format('M d, Y') }}
                    @if($model->last_login_at)
                        · {{ __('admin.admins_section.last_login') }} {{ $model->last_login_at->diffForHumans() }}
                    @endif
                </p>
            @endif
        </div>
        <div class="flex gap-2">
            <a href="{{ route('admin.admins.index') }}" class="btn btn-secondary">{{ __('common.cancel') }}</a>
            <button type="submit" class="btn btn-primary" id="save-btn">
                {{ $isEdit ? __('common.save') : __('admin.admins_section.create_admin') }}
            </button>
        </div>
    </div>

    {{-- ── Set-password notice (create mode only) ─────────────────────── --}}
    @if(!$isEdit)
        <div class="border border-blue-200 bg-blue-50 rounded-lg px-4 py-3 text-sm text-blue-800">
            {{ __('admin.admins_section.set_password_link_notice') }}
        </div>
    @endif

    {{-- ── Two-column layout ───────────────────────────────────────────── --}}
    <div class="flex flex-col lg:flex-row gap-6 items-start">

        {{-- ═══════ LEFT: Admin Details ═══════ --}}
        <div class="flex-1 min-w-0 space-y-4">
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h2 class="text-sm font-semibold text-gray-900">{{ __('admin.admins_section.admin_details') }}</h2>
                </div>
                <div class="px-5 py-5 space-y-4">

                    <x-form-input
                        name="name"
                        label="{{ __('admin.admins_section.full_name') }}"
                        type="text"
                        :value="$val('name')"
                        placeholder="{{ __('admin.admins_section.full_name_placeholder') }}"
                        required
                    />

                    <x-form-input
                        name="email"
                        label="{{ __('common.email') }}"
                        type="email"
                        :value="$val('email')"
                        placeholder="admin@example.com"
                        required
                    />

                    <x-form-input
                        name="phone"
                        label="{{ __('common.phone') }}"
                        type="text"
                        :value="$val('phone')"
                        placeholder="+20 10 0000 0000"
                    />

                    <x-form-select
                        name="country_id"
                        label="{{ __('common.country') }}"
                        :options="$countryOptions"
                        :value="$val('country_id')"
                        placeholder="— {{ __('admin.admins_section.no_country') }} —"
                        select2
                    />

                    <x-form-select
                        name="status"
                        label="{{ __('common.status') }}"
                        :options="$statusOptions"
                        :value="$val('status', 'active')"
                        required
                    />

                </div>
            </div>

            {{-- ── Edit mode: Reset Password ──────────────────────────── --}}
            @if($isEdit)
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
                    <div class="px-5 py-4 border-b border-gray-100">
                        <h2 class="text-sm font-semibold text-gray-900">{{ __('admin.admins_section.password') }}</h2>
                    </div>
                    <div class="px-5 py-5 flex items-center justify-between">
                        <p class="text-sm text-gray-500">
                            {{ __('admin.admins_section.reset_password_notice') }}
                        </p>
                        <div class="flex shrink-0 gap-2 ml-4">
                            @if(auth('admin')->user()?->can('admins.edit'))
                                <button type="button" id="resend-credentials-btn"
                                        data-url="{{ route('admin.admins.resend-credentials', $model->id) }}"
                                        class="btn btn-secondary btn-sm">
                                    {{ __('admin.admins_section.resend_credentials') }}
                                </button>
                            @endif
                            <button type="button" id="reset-password-btn"
                                    data-url="{{ route('admin.admins.reset-password', $model->id) }}"
                                    class="btn btn-secondary btn-sm">
                                {{ __('auth.reset_password') }}
                            </button>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        {{-- ═══════ RIGHT: Roles & Permissions ═══════ --}}
        <div class="w-full lg:w-80 shrink-0 space-y-4">

            {{-- Roles card --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h2 class="text-sm font-semibold text-gray-900">{{ __('admin.roles_section.title') }}</h2>
                    <p class="text-xs text-gray-500 mt-0.5">{{ __('admin.admins_section.at_least_one_role') }}</p>
                </div>
                <div class="px-5 py-4 space-y-2">
                    @error('roles')
                        <p class="text-xs text-red-600 mb-2">{{ $message }}</p>
                    @enderror

                    @foreach($allRoles as $role)
                        <label class="flex items-center gap-2.5 cursor-pointer group">
                            <input
                                type="checkbox"
                                name="roles[]"
                                value="{{ $role->name }}"
                                x-model="selectedRoles"
                                class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                            >
                            <span class="text-sm text-gray-700 group-hover:text-gray-900">{{ $role->name }}</span>
                            <span class="text-xs text-gray-400 ml-auto">{{ $role->permissions->count() }} {{ __('admin.admins_section.perms') }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            {{-- Vendor scope restriction card --}}
            @if($isEdit && !$model->hasRole('super_admin'))
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
                    <div class="px-5 py-4 border-b border-gray-100">
                        <h2 class="text-sm font-semibold text-gray-900">{{ __('admin.admins_section.vendor_scope_restriction') }}</h2>
                    </div>
                    <div class="px-5 py-4">
                        <label class="flex items-start gap-2.5 cursor-pointer group">
                            <input
                                type="checkbox"
                                name="vendors_assigned_only"
                                value="1"
                                {{ old('vendors_assigned_only', $vendorsAssignedOnly ?? false) ? 'checked' : '' }}
                                class="mt-0.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                            >
                            <span class="text-sm text-gray-700 group-hover:text-gray-900">
                                {{ __('admin.admins_section.vendor_scope_restrict_label') }}
                            </span>
                        </label>
                        <p class="text-xs text-gray-500 mt-2">
                            {{ __('admin.admins_section.vendor_scope_restrict_hint') }}
                        </p>
                    </div>
                </div>
            @endif

            {{-- Portal content permissions (per-employee grant on top of roles) --}}
            @if($isEdit && !$model->hasRole('super_admin'))
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm" data-portal-permissions>
                    <div class="px-5 py-4 border-b border-gray-100">
                        <h2 class="text-sm font-semibold text-gray-900">{{ __('admin.portal_permissions.staff_title') }}</h2>
                        <p class="text-xs text-gray-500 mt-0.5">{{ __('admin.portal_permissions.staff_hint') }}</p>
                    </div>
                    <div class="px-5 py-4 space-y-3">
                        <input type="hidden" name="portal_permissions_present" value="1">
                        @foreach($portalPermissionNames as $permName)
                            <label class="flex items-start gap-2.5 cursor-pointer group">
                                <input
                                    type="checkbox"
                                    name="portal_permissions[]"
                                    value="{{ $permName }}"
                                    x-model="directPortal"
                                    class="mt-0.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                >
                                <span>
                                    <span class="text-sm text-gray-700 group-hover:text-gray-900">{{ \App\Support\PortalPermissions::label($permName) }}</span>
                                    <span x-show="viaRole('{{ $permName }}')" x-cloak class="ms-1 text-[10px] rounded bg-green-100 text-green-700 px-1.5 py-0.5">{{ __('admin.portal_permissions.staff_via_role') }}</span>
                                    <span class="block text-xs text-gray-400">{{ \App\Support\PortalPermissions::description($permName) }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Effective permissions card --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
                <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-gray-900">{{ __('admin.admins_section.effective_permissions') }}</h2>
                    <span class="text-xs text-gray-500 bg-gray-100 rounded-full px-2 py-0.5" x-text="effectivePermissions.length"></span>
                </div>
                <div class="px-5 py-4 max-h-72 overflow-y-auto">
                    <template x-if="!effectivePermissions.length">
                        <p class="text-xs text-gray-400 italic">{{ __('admin.admins_section.select_role_hint') }}</p>
                    </template>
                    <ul class="space-y-0.5">
                        <template x-for="perm in effectivePermissions" :key="perm">
                            <li class="text-xs text-gray-600 font-mono py-0.5 border-b border-gray-50 last:border-0" x-text="perm"></li>
                        </template>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
