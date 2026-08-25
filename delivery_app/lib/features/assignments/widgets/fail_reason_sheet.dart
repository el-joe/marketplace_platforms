import 'package:flutter/material.dart';

import '../../../shared/models/assignment.dart';
import '../../../shared/widgets/primary_button.dart';

class FailResult {
  final String failureReason;
  final String? failureNotes;
  final String? customerRejectionReason;

  FailResult({required this.failureReason, this.failureNotes, this.customerRejectionReason});
}

Future<FailResult?> showFailReasonSheet(BuildContext context) {
  return showModalBottomSheet<FailResult>(
    context: context,
    isScrollControlled: true,
    shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(20))),
    builder: (context) => Padding(
      padding: EdgeInsets.only(
        left: 20,
        right: 20,
        top: 20,
        bottom: MediaQuery.of(context).viewInsets.bottom + 20,
      ),
      child: const _FailSheetContent(),
    ),
  );
}

class _FailSheetContent extends StatefulWidget {
  const _FailSheetContent();

  @override
  State<_FailSheetContent> createState() => _FailSheetContentState();
}

class _FailSheetContentState extends State<_FailSheetContent> {
  String? _reason;
  final _notesController = TextEditingController();
  final _rejectionController = TextEditingController();

  @override
  void dispose() {
    _notesController.dispose();
    _rejectionController.dispose();
    super.dispose();
  }

  bool get _canSubmit {
    if (_reason == null) return false;
    if (_reason == 'customer_rejected' && _rejectionController.text.trim().isEmpty) return false;
    return true;
  }

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text('Mark Delivery as Failed', style: Theme.of(context).textTheme.titleLarge, textAlign: TextAlign.center),
          const SizedBox(height: 20),
          DropdownButtonFormField<String>(
            initialValue: _reason,
            decoration: const InputDecoration(labelText: 'Reason'),
            items: failureReasons
                .map((r) => DropdownMenuItem(value: r, child: Text(failureReasonLabel(r))))
                .toList(),
            onChanged: (value) => setState(() => _reason = value),
          ),
          if (_reason == 'customer_rejected') ...[
            const SizedBox(height: 16),
            TextField(
              controller: _rejectionController,
              maxLines: 2,
              onChanged: (_) => setState(() {}),
              decoration: const InputDecoration(labelText: 'Customer rejection reason'),
            ),
          ],
          const SizedBox(height: 16),
          TextField(
            controller: _notesController,
            maxLines: 3,
            decoration: const InputDecoration(labelText: 'Additional notes (optional)'),
          ),
          const SizedBox(height: 20),
          PrimaryButton(
            label: 'Confirm Failure',
            onPressed: _canSubmit
                ? () => Navigator.of(context).pop(
                      FailResult(
                        failureReason: _reason!,
                        failureNotes: _notesController.text.trim().isEmpty ? null : _notesController.text.trim(),
                        customerRejectionReason:
                            _rejectionController.text.trim().isEmpty ? null : _rejectionController.text.trim(),
                      ),
                    )
                : null,
          ),
        ],
      ),
    );
  }
}
