import 'package:flutter/material.dart';

import '../../../core/theme/app_theme.dart';
import '../../../core/utils/money_formatter.dart';
import '../../../shared/widgets/primary_button.dart';

class CodCollectionResult {
  final int amountCollected;
  final String? discrepancyNote;

  CodCollectionResult({required this.amountCollected, this.discrepancyNote});
}

/// Bottom sheet collecting the COD amount actually collected from the
/// customer. A discrepancy note is required when the collected amount
/// differs from the expected amount by more than 5%, or when the server
/// forces one via [forceDiscrepancyNote] after a 422 response.
Future<CodCollectionResult?> showCodCollectionSheet(
  BuildContext context, {
  required int expectedAmount,
  required String currency,
  bool forceDiscrepancyNote = false,
}) {
  return showModalBottomSheet<CodCollectionResult>(
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
      child: _CodSheetContent(
        expectedAmount: expectedAmount,
        currency: currency,
        forceDiscrepancyNote: forceDiscrepancyNote,
      ),
    ),
  );
}

class _CodSheetContent extends StatefulWidget {
  final int expectedAmount;
  final String currency;
  final bool forceDiscrepancyNote;

  const _CodSheetContent({
    required this.expectedAmount,
    required this.currency,
    required this.forceDiscrepancyNote,
  });

  @override
  State<_CodSheetContent> createState() => _CodSheetContentState();
}

class _CodSheetContentState extends State<_CodSheetContent> {
  final _amountController = TextEditingController();
  final _noteController = TextEditingController();

  @override
  void initState() {
    super.initState();
    _amountController.text = widget.expectedAmount.toString();
    _amountController.addListener(() => setState(() {}));
  }

  @override
  void dispose() {
    _amountController.dispose();
    _noteController.dispose();
    super.dispose();
  }

  int? get _collected => int.tryParse(_amountController.text.trim());

  bool get _needsNote {
    final collected = _collected;
    if (collected == null) return false;
    if (widget.forceDiscrepancyNote) return true;
    if (widget.expectedAmount == 0) return collected != 0;
    final diff = (collected - widget.expectedAmount).abs();
    return diff > (widget.expectedAmount * 0.05);
  }

  bool get _canSubmit {
    final collected = _collected;
    if (collected == null || collected < 0) return false;
    if (_needsNote && _noteController.text.trim().isEmpty) return false;
    return true;
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Text('Collect Cash on Delivery', style: Theme.of(context).textTheme.titleLarge, textAlign: TextAlign.center),
        const SizedBox(height: 8),
        Text(
          'Expected: ${MoneyFormatter.format(widget.expectedAmount, widget.currency)}',
          textAlign: TextAlign.center,
          style: TextStyle(color: Colors.grey.shade600),
        ),
        const SizedBox(height: 20),
        TextField(
          controller: _amountController,
          keyboardType: const TextInputType.numberWithOptions(decimal: false),
          decoration: InputDecoration(
            labelText: 'Amount collected',
            prefixText: '${widget.currency} ',
          ),
        ),
        if (_needsNote) ...[
          const SizedBox(height: 16),
          Text(
            'Amount differs from expected — please explain.',
            style: TextStyle(color: AppTheme.warning, fontSize: 13),
          ),
          const SizedBox(height: 8),
          TextField(
            controller: _noteController,
            maxLines: 3,
            onChanged: (_) => setState(() {}),
            decoration: const InputDecoration(labelText: 'Discrepancy note'),
          ),
        ],
        const SizedBox(height: 20),
        PrimaryButton(
          label: 'Confirm Collection',
          onPressed: _canSubmit
              ? () => Navigator.of(context).pop(
                    CodCollectionResult(
                      amountCollected: _collected!,
                      discrepancyNote: _noteController.text.trim().isEmpty ? null : _noteController.text.trim(),
                    ),
                  )
              : null,
        ),
      ],
    );
  }
}
