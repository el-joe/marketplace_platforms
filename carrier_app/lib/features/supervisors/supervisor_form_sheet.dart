import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/api_exception.dart';
import '../../core/theme/app_theme.dart';
import '../../shared/models/supervisor.dart';
import '../../shared/widgets/primary_button.dart';
import 'supervisors_provider.dart';

const _allPermissions = ['manage_agents', 'view_orders', 'assign_orders', 'view_reports'];

/// Create/edit sheet for a supervisor. Pass [supervisor] to edit; omit to
/// create a new one (invite flow — backend emails a temp password).
class SupervisorFormSheet extends ConsumerStatefulWidget {
  final Supervisor? supervisor;

  const SupervisorFormSheet({super.key, this.supervisor});

  bool get isEdit => supervisor != null;

  @override
  ConsumerState<SupervisorFormSheet> createState() => _SupervisorFormSheetState();
}

class _SupervisorFormSheetState extends ConsumerState<SupervisorFormSheet> {
  final _formKey = GlobalKey<FormState>();
  late final _nameController = TextEditingController(text: widget.supervisor?.name ?? '');
  late final _emailController = TextEditingController(text: widget.supervisor?.email ?? '');
  late final _phoneController = TextEditingController(text: widget.supervisor?.phone ?? '');
  late final Set<String> _permissions = {...(widget.supervisor?.permissions ?? const [])};
  late bool _isActive = widget.supervisor?.isActive ?? true;
  bool _loading = false;
  String? _error;
  Map<String, dynamic>? _fieldErrors;

  @override
  void dispose() {
    _nameController.dispose();
    _emailController.dispose();
    _phoneController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    if (_permissions.isEmpty) {
      setState(() => _error = 'Select at least one permission.');
      return;
    }
    setState(() {
      _loading = true;
      _error = null;
      _fieldErrors = null;
    });

    try {
      final repository = ref.read(supervisorsRepositoryProvider);
      if (widget.isEdit) {
        await repository.update(widget.supervisor!.id, {
          'name': _nameController.text.trim(),
          'phone': _phoneController.text.trim(),
          'permissions': _permissions.toList(),
          'is_active': _isActive,
        });
      } else {
        await repository.create({
          'name': _nameController.text.trim(),
          'email': _emailController.text.trim(),
          'phone': _phoneController.text.trim(),
          'permissions': _permissions.toList(),
        });
      }
      ref.invalidate(supervisorsProvider);
      if (mounted) Navigator.of(context).pop();
    } on ApiException catch (e) {
      setState(() {
        _fieldErrors = e.errors;
        _error = e.errors == null ? e.message : null;
      });
    } catch (_) {
      setState(() => _error = 'Failed to save supervisor.');
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return SafeArea(
      child: Padding(
        padding: EdgeInsets.only(
          left: 16,
          right: 16,
          top: 16,
          bottom: MediaQuery.of(context).viewInsets.bottom + 16,
        ),
        child: SingleChildScrollView(
          child: Form(
            key: _formKey,
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Text(widget.isEdit ? 'Edit Supervisor' : 'Invite Supervisor',
                    style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w700)),
                const SizedBox(height: 16),
                TextFormField(
                  controller: _nameController,
                  decoration: InputDecoration(labelText: 'Name', errorText: _fieldErrors?['name']?.first?.toString()),
                  validator: (v) => (v == null || v.trim().isEmpty) ? 'Required' : null,
                ),
                const SizedBox(height: 12),
                if (!widget.isEdit) ...[
                  TextFormField(
                    controller: _emailController,
                    keyboardType: TextInputType.emailAddress,
                    decoration:
                        InputDecoration(labelText: 'Email', errorText: _fieldErrors?['email']?.first?.toString()),
                    validator: (v) => (v == null || v.trim().isEmpty) ? 'Required' : null,
                  ),
                  const SizedBox(height: 12),
                ],
                TextFormField(
                  controller: _phoneController,
                  keyboardType: TextInputType.phone,
                  decoration: const InputDecoration(labelText: 'Phone (optional)'),
                ),
                const SizedBox(height: 16),
                const Text('Permissions', style: TextStyle(fontWeight: FontWeight.w600)),
                const SizedBox(height: 8),
                ..._allPermissions.map((p) => CheckboxListTile(
                      contentPadding: EdgeInsets.zero,
                      controlAffinity: ListTileControlAffinity.leading,
                      title: Text(p.replaceAll('_', ' ')),
                      value: _permissions.contains(p),
                      onChanged: (checked) => setState(() {
                        if (checked ?? false) {
                          _permissions.add(p);
                        } else {
                          _permissions.remove(p);
                        }
                      }),
                    )),
                if (widget.isEdit) ...[
                  const SizedBox(height: 8),
                  SwitchListTile(
                    contentPadding: EdgeInsets.zero,
                    title: const Text('Active'),
                    value: _isActive,
                    onChanged: (v) => setState(() => _isActive = v),
                  ),
                ],
                if (_error != null) ...[
                  const SizedBox(height: 12),
                  Text(_error!, style: const TextStyle(color: AppTheme.danger)),
                ],
                const SizedBox(height: 16),
                PrimaryButton(
                  label: widget.isEdit ? 'Save Changes' : 'Invite Supervisor',
                  onPressed: _submit,
                  loading: _loading,
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
