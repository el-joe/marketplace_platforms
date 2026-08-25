import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/api/api_exception.dart';
import '../../core/theme/app_theme.dart';
import '../../shared/models/agent.dart';
import '../../shared/models/zone.dart';
import '../../shared/widgets/error_view.dart';
import '../../shared/widgets/loading_view.dart';
import '../../shared/widgets/primary_button.dart';
import 'agents_provider.dart';

/// Shared create/edit form. When [agentId] is null this creates a new agent
/// (POST /agents); otherwise it edits the existing one (PUT /agents/:id).
class AgentFormScreen extends ConsumerStatefulWidget {
  final String? agentId;

  const AgentFormScreen({super.key, this.agentId});

  bool get isEdit => agentId != null;

  @override
  ConsumerState<AgentFormScreen> createState() => _AgentFormScreenState();
}

class _AgentFormScreenState extends ConsumerState<AgentFormScreen> {
  final _formKey = GlobalKey<FormState>();
  final _nameController = TextEditingController();
  final _emailController = TextEditingController();
  final _phoneController = TextEditingController();
  final _passwordController = TextEditingController();
  final _licensePlateController = TextEditingController();
  final _nationalIdController = TextEditingController();
  final _emergencyNameController = TextEditingController();
  final _emergencyPhoneController = TextEditingController();

  String _vehicleType = 'motorcycle';
  int? _zoneId;
  bool _loading = false;
  bool _initialized = false;
  Map<String, dynamic>? _fieldErrors;
  String? _generalError;

  static const _vehicleTypes = ['motorcycle', 'car', 'van', 'bicycle'];

  @override
  void dispose() {
    _nameController.dispose();
    _emailController.dispose();
    _phoneController.dispose();
    _passwordController.dispose();
    _licensePlateController.dispose();
    _nationalIdController.dispose();
    _emergencyNameController.dispose();
    _emergencyPhoneController.dispose();
    super.dispose();
  }

  void _prefill(Agent agent) {
    if (_initialized) return;
    _initialized = true;
    _nameController.text = agent.name;
    _emailController.text = agent.email ?? '';
    _phoneController.text = agent.phone ?? '';
    _licensePlateController.text = agent.vehiclePlate ?? '';
    _nationalIdController.text = agent.nationalId ?? '';
    _emergencyNameController.text = agent.emergencyContactName ?? '';
    _emergencyPhoneController.text = agent.emergencyContactPhone ?? '';
    _vehicleType = agent.vehicleType ?? 'motorcycle';
    _zoneId = agent.zone?.id;
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() {
      _loading = true;
      _fieldErrors = null;
      _generalError = null;
    });

    try {
      final repository = ref.read(agentsRepositoryProvider);
      if (widget.isEdit) {
        await repository.update(widget.agentId!, {
          'name': _nameController.text.trim(),
          'phone': _phoneController.text.trim(),
          'vehicle_type': _vehicleType,
          if (_nationalIdController.text.trim().isNotEmpty) 'national_id': _nationalIdController.text.trim(),
          if (_licensePlateController.text.trim().isNotEmpty) 'vehicle_plate': _licensePlateController.text.trim(),
          if (_emergencyNameController.text.trim().isNotEmpty)
            'emergency_contact_name': _emergencyNameController.text.trim(),
          if (_emergencyPhoneController.text.trim().isNotEmpty)
            'emergency_contact_phone': _emergencyPhoneController.text.trim(),
          if (_passwordController.text.isNotEmpty) 'password': _passwordController.text,
        });
        ref.invalidate(agentDetailProvider(widget.agentId!));
      } else {
        await repository.create({
          'name': _nameController.text.trim(),
          'email': _emailController.text.trim(),
          'phone': _phoneController.text.trim(),
          'password': _passwordController.text,
          'vehicle_type': _vehicleType,
          if (_licensePlateController.text.trim().isNotEmpty) 'license_plate': _licensePlateController.text.trim(),
          if (_zoneId != null) 'zone_id': _zoneId,
        });
      }
      ref.invalidate(agentsProvider);
      if (mounted) context.pop();
    } on ApiException catch (e) {
      setState(() {
        _fieldErrors = e.errors;
        _generalError = e.errors == null ? e.message : null;
      });
    } catch (_) {
      setState(() => _generalError = 'Failed to save agent.');
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final zonesAsync = ref.watch(zonesProvider);
    final agentAsync = widget.isEdit ? ref.watch(agentDetailProvider(widget.agentId!)) : null;

    if (widget.isEdit) {
      return Scaffold(
        appBar: AppBar(title: const Text('Edit Agent')),
        body: agentAsync!.when(
          loading: () => const LoadingView(),
          error: (e, _) => ErrorView(message: e is ApiException ? e.message : 'Failed to load agent.'),
          data: (agent) {
            _prefill(agent);
            return _buildForm(context, zonesAsync, showEmailPassword: false);
          },
        ),
      );
    }

    return Scaffold(
      appBar: AppBar(title: const Text('New Agent')),
      body: _buildForm(context, zonesAsync, showEmailPassword: true),
    );
  }

  Widget _buildForm(BuildContext context, AsyncValue<List<Zone>> zonesAsync, {required bool showEmailPassword}) {
    return SingleChildScrollView(
      padding: const EdgeInsets.all(16),
      child: Form(
        key: _formKey,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            TextFormField(
              controller: _nameController,
              decoration: InputDecoration(labelText: 'Name', errorText: _fieldErrors?['name']?.first?.toString()),
              validator: (v) => (v == null || v.trim().isEmpty) ? 'Required' : null,
            ),
            const SizedBox(height: 12),
            if (showEmailPassword) ...[
              TextFormField(
                controller: _emailController,
                keyboardType: TextInputType.emailAddress,
                decoration: InputDecoration(labelText: 'Email', errorText: _fieldErrors?['email']?.first?.toString()),
                validator: (v) => (v == null || v.trim().isEmpty) ? 'Required' : null,
              ),
              const SizedBox(height: 12),
            ],
            TextFormField(
              controller: _phoneController,
              keyboardType: TextInputType.phone,
              decoration: InputDecoration(labelText: 'Phone', errorText: _fieldErrors?['phone']?.first?.toString()),
              validator: (v) => (v == null || v.trim().isEmpty) ? 'Required' : null,
            ),
            const SizedBox(height: 12),
            if (showEmailPassword) ...[
              TextFormField(
                controller: _passwordController,
                obscureText: true,
                decoration:
                    InputDecoration(labelText: 'Password', errorText: _fieldErrors?['password']?.first?.toString()),
                validator: (v) => (v == null || v.length < 8) ? 'Min 8 characters' : null,
              ),
              const SizedBox(height: 12),
            ] else ...[
              TextFormField(
                controller: _passwordController,
                obscureText: true,
                decoration: const InputDecoration(labelText: 'New password (optional)'),
                validator: (v) => (v != null && v.isNotEmpty && v.length < 8) ? 'Min 8 characters' : null,
              ),
              const SizedBox(height: 12),
            ],
            DropdownButtonFormField<String>(
              initialValue: _vehicleType,
              decoration: const InputDecoration(labelText: 'Vehicle type'),
              items: _vehicleTypes
                  .map((v) => DropdownMenuItem(value: v, child: Text(v[0].toUpperCase() + v.substring(1))))
                  .toList(),
              onChanged: (v) => setState(() => _vehicleType = v ?? _vehicleType),
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _licensePlateController,
              decoration: const InputDecoration(labelText: 'Vehicle plate (optional)'),
            ),
            if (showEmailPassword) ...[
              const SizedBox(height: 12),
              zonesAsync.when(
                loading: () => const SizedBox.shrink(),
                error: (_, __) => const SizedBox.shrink(),
                data: (zones) => DropdownButtonFormField<int?>(
                  initialValue: _zoneId,
                  decoration: const InputDecoration(labelText: 'Zone (optional)'),
                  items: [
                    const DropdownMenuItem(value: null, child: Text('Unassigned')),
                    ...zones.map((z) => DropdownMenuItem(value: z.id, child: Text(z.name))),
                  ],
                  onChanged: (v) => setState(() => _zoneId = v),
                ),
              ),
            ],
            if (!showEmailPassword) ...[
              const SizedBox(height: 12),
              TextFormField(
                controller: _nationalIdController,
                decoration: const InputDecoration(labelText: 'National ID (optional)'),
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: _emergencyNameController,
                decoration: const InputDecoration(labelText: 'Emergency contact name (optional)'),
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: _emergencyPhoneController,
                decoration: const InputDecoration(labelText: 'Emergency contact phone (optional)'),
              ),
            ],
            if (_generalError != null) ...[
              const SizedBox(height: 16),
              Text(_generalError!, style: const TextStyle(color: AppTheme.danger)),
            ],
            const SizedBox(height: 24),
            PrimaryButton(label: widget.isEdit ? 'Save Changes' : 'Create Agent', onPressed: _submit, loading: _loading),
          ],
        ),
      ),
    );
  }
}
