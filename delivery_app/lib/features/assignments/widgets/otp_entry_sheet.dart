import 'package:flutter/material.dart';
import 'package:pin_code_fields/pin_code_fields.dart';

import '../../../core/theme/app_theme.dart';
import '../../../shared/widgets/primary_button.dart';

/// Bottom sheet collecting the 6-digit delivery OTP from the customer.
/// Returns the entered code, or null if dismissed.
Future<String?> showOtpEntrySheet(BuildContext context, {String? errorMessage, int? remainingAttempts}) {
  return showModalBottomSheet<String>(
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
      child: _OtpSheetContent(errorMessage: errorMessage, remainingAttempts: remainingAttempts),
    ),
  );
}

class _OtpSheetContent extends StatefulWidget {
  final String? errorMessage;
  final int? remainingAttempts;

  const _OtpSheetContent({this.errorMessage, this.remainingAttempts});

  @override
  State<_OtpSheetContent> createState() => _OtpSheetContentState();
}

class _OtpSheetContentState extends State<_OtpSheetContent> {
  String _code = '';

  @override
  Widget build(BuildContext context) {
    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        Text('Enter Delivery OTP', style: Theme.of(context).textTheme.titleLarge),
        const SizedBox(height: 8),
        Text(
          'Ask the customer for the 6-digit code sent to them.',
          textAlign: TextAlign.center,
          style: TextStyle(color: Colors.grey.shade600),
        ),
        const SizedBox(height: 24),
        PinCodeTextField(
          appContext: context,
          length: 6,
          keyboardType: TextInputType.number,
          animationType: AnimationType.fade,
          pinTheme: PinTheme(
            shape: PinCodeFieldShape.box,
            borderRadius: BorderRadius.circular(10),
            fieldHeight: 48,
            fieldWidth: 42,
            activeColor: AppTheme.primary,
            selectedColor: AppTheme.primary,
            inactiveColor: Colors.grey.shade300,
          ),
          onChanged: (value) => setState(() => _code = value),
          onCompleted: (value) => setState(() => _code = value),
        ),
        if (widget.errorMessage != null) ...[
          const SizedBox(height: 12),
          Text(widget.errorMessage!, style: const TextStyle(color: AppTheme.danger)),
        ],
        if (widget.remainingAttempts != null) ...[
          const SizedBox(height: 4),
          Text('${widget.remainingAttempts} attempt(s) remaining', style: TextStyle(color: Colors.grey.shade600)),
        ],
        const SizedBox(height: 20),
        PrimaryButton(
          label: 'Verify & Confirm',
          onPressed: _code.length == 6 ? () => Navigator.of(context).pop(_code) : null,
        ),
      ],
    );
  }
}
