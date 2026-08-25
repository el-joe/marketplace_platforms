import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:image_picker/image_picker.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../core/api/api_exception.dart';
import '../../core/theme/app_theme.dart';
import '../../core/utils/date_formatter.dart';
import '../../core/utils/money_formatter.dart';
import '../../shared/models/assignment.dart';
import '../../shared/widgets/d_card.dart';
import '../../shared/widgets/error_view.dart';
import '../../shared/widgets/loading_view.dart';
import '../../shared/widgets/primary_button.dart';
import '../../shared/widgets/status_chip.dart';
import 'assignments_provider.dart';
import 'widgets/cod_collection_sheet.dart';
import 'widgets/fail_reason_sheet.dart';
import 'widgets/otp_entry_sheet.dart';

class AssignmentDetailScreen extends ConsumerStatefulWidget {
  final int assignmentId;

  const AssignmentDetailScreen({super.key, required this.assignmentId});

  @override
  ConsumerState<AssignmentDetailScreen> createState() => _AssignmentDetailScreenState();
}

class _AssignmentDetailScreenState extends ConsumerState<AssignmentDetailScreen> {
  bool _busy = false;

  Future<void> _run(Future<void> Function() action, {String? failureFallback}) async {
    setState(() => _busy = true);
    try {
      await action();
    } on ApiException catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
      }
    } catch (_) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(failureFallback ?? 'Something went wrong.')));
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _accept() => _run(() => ref.read(assignmentDetailProvider(widget.assignmentId).notifier).accept());

  Future<void> _pickUp() =>
      _run(() => ref.read(assignmentDetailProvider(widget.assignmentId).notifier).markPickedUp());

  Future<void> _confirmDelivery(Assignment assignment) async {
    String? otpError;
    int? remaining;
    String? verifiedOtp;

    while (true) {
      final otp = await showOtpEntrySheet(context, errorMessage: otpError, remainingAttempts: remaining);
      if (otp == null || !mounted) return;

      try {
        await ref.read(assignmentDetailProvider(widget.assignmentId).notifier).verifyOtp(otp);
        verifiedOtp = otp;
        break;
      } on ApiException catch (e) {
        if (e.isOtpLocked) {
          if (mounted) {
            ScaffoldMessenger.of(context)
                .showSnackBar(SnackBar(content: Text(e.message), backgroundColor: AppTheme.danger));
          }
          return;
        }
        otpError = e.message;
        remaining = e.remainingOtpAttempts;
        continue;
      }
    }

    if (!mounted) return;

    int? codAmountCollected;
    String? discrepancyNote;

    if (assignment.isCod) {
      final expected = assignment.effectiveCodAmount ?? 0;
      final result = await showCodCollectionSheet(
        context,
        expectedAmount: expected,
        currency: assignment.currency ?? 'AED',
      );
      if (result == null) return;
      codAmountCollected = result.amountCollected;
      discrepancyNote = result.discrepancyNote;
    }

    final picked = await ImagePicker().pickImage(source: ImageSource.camera, imageQuality: 80);
    if (picked == null) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(const SnackBar(content: Text('A delivery proof photo is required.')));
      }
      return;
    }
    final proofImage = File(picked.path);

    Future<void> submit(String? note) => _run(() async {
          try {
            await ref.read(assignmentDetailProvider(widget.assignmentId).notifier).deliver(
                  otpCode: verifiedOtp!,
                  proofImage: proofImage,
                  codAmountCollected: codAmountCollected,
                  discrepancyNote: note ?? discrepancyNote,
                );
          } on ApiException catch (e) {
            if (e.requiresDiscrepancyNote && mounted) {
              final result = await showCodCollectionSheet(
                context,
                expectedAmount: assignment.effectiveCodAmount ?? 0,
                currency: assignment.currency ?? 'AED',
                forceDiscrepancyNote: true,
              );
              if (result != null) {
                codAmountCollected = result.amountCollected;
                await submit(result.discrepancyNote);
              }
              return;
            }
            rethrow;
          }
        });

    await submit(discrepancyNote);
  }

  Future<void> _markFailed() async {
    final result = await showFailReasonSheet(context);
    if (result == null) return;
    await _run(() => ref.read(assignmentDetailProvider(widget.assignmentId).notifier).fail(
          failureReason: result.failureReason,
          failureNotes: result.failureNotes,
          customerRejectionReason: result.customerRejectionReason,
        ));
  }

  Future<void> _call(String? phone) async {
    if (phone == null) return;
    final uri = Uri(scheme: 'tel', path: phone);
    if (await canLaunchUrl(uri)) await launchUrl(uri);
  }

  @override
  Widget build(BuildContext context) {
    final assignmentAsync = ref.watch(assignmentDetailProvider(widget.assignmentId));

    return Scaffold(
      appBar: AppBar(title: const Text('Delivery Details')),
      body: assignmentAsync.when(
        loading: () => const LoadingView(),
        error: (e, _) => ErrorView(message: e is ApiException ? e.message : 'Failed to load delivery.'),
        data: (assignment) => RefreshIndicator(
          onRefresh: () => ref.refresh(assignmentDetailProvider(widget.assignmentId).future),
          child: ListView(
            padding: const EdgeInsets.all(16),
            children: [
              DCard(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Text(assignment.subOrderNumber ?? '#${assignment.id}',
                            style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
                        const Spacer(),
                        StatusChip(status: assignment.status),
                      ],
                    ),
                    const SizedBox(height: 12),
                    _timelineRow('Assigned', assignment.assignedAt),
                    _timelineRow('Accepted', assignment.acceptedAt),
                    _timelineRow('Picked up', assignment.pickedUpAt),
                    _timelineRow('Delivered', assignment.deliveredAt),
                    _timelineRow('Failed', assignment.failedAt),
                  ],
                ),
              ),
              const SizedBox(height: 16),
              DCard(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('Customer', style: Theme.of(context).textTheme.titleMedium),
                    const SizedBox(height: 8),
                    Text(assignment.fullAddress?.recipientName ?? assignment.recipientName ?? '-'),
                    const SizedBox(height: 4),
                    Row(
                      children: [
                        Expanded(child: Text(assignment.fullAddress?.recipientPhone ?? '')),
                        IconButton(
                          icon: const Icon(Icons.call, color: AppTheme.success),
                          onPressed: () => _call(assignment.fullAddress?.recipientPhone),
                        ),
                      ],
                    ),
                    const Divider(height: 24),
                    Text('Delivery Address', style: Theme.of(context).textTheme.titleMedium),
                    const SizedBox(height: 8),
                    Text(assignment.fullAddress?.formatted ?? assignment.deliveryAddressLine ?? '-'),
                    if (assignment.fullAddress?.landmark != null) ...[
                      const SizedBox(height: 4),
                      Text('Landmark: ${assignment.fullAddress!.landmark}'),
                    ],
                  ],
                ),
              ),
              if (assignment.items.isNotEmpty) ...[
                const SizedBox(height: 16),
                DCard(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text('Items', style: Theme.of(context).textTheme.titleMedium),
                      const SizedBox(height: 8),
                      ...assignment.items.map((i) => Padding(
                            padding: const EdgeInsets.symmetric(vertical: 4),
                            child: Text('${i.quantity} x ${i.name}'),
                          )),
                    ],
                  ),
                ),
              ],
              if (assignment.isCod) ...[
                const SizedBox(height: 16),
                DCard(
                  child: Row(
                    children: [
                      const Icon(Icons.payments_outlined, color: AppTheme.warning),
                      const SizedBox(width: 8),
                      Text(
                        'COD to collect: ${assignment.effectiveCodAmount != null && assignment.currency != null ? MoneyFormatter.format(assignment.effectiveCodAmount!, assignment.currency!) : '-'}',
                        style: const TextStyle(fontWeight: FontWeight.w600),
                      ),
                    ],
                  ),
                ),
              ],
              if (assignment.status == 'failed' && assignment.failureReason != null) ...[
                const SizedBox(height: 16),
                DCard(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text('Failure reason: ${failureReasonLabel(assignment.failureReason!)}'),
                      if (assignment.failureNotes != null) Text(assignment.failureNotes!),
                    ],
                  ),
                ),
              ],
              const SizedBox(height: 24),
              _actionButtons(assignment),
            ],
          ),
        ),
      ),
    );
  }

  Widget _timelineRow(String label, DateTime? dt) {
    if (dt == null) return const SizedBox.shrink();
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 2),
      child: Row(
        children: [
          SizedBox(width: 80, child: Text(label, style: TextStyle(color: Colors.grey.shade600))),
          Text(DateFormatter.dateTime(dt)),
        ],
      ),
    );
  }

  Widget _actionButtons(Assignment assignment) {
    switch (assignment.status) {
      case 'assigned':
        return PrimaryButton(label: 'Accept Delivery', onPressed: _busy ? null : _accept, loading: _busy);
      case 'accepted':
        return PrimaryButton(label: 'Mark as Picked Up', onPressed: _busy ? null : _pickUp, loading: _busy);
      case 'picked_up':
        return Column(
          children: [
            PrimaryButton(
              label: 'Confirm Delivery',
              onPressed: _busy ? null : () => _confirmDelivery(assignment),
              loading: _busy,
            ),
            const SizedBox(height: 12),
            SizedBox(
              width: double.infinity,
              child: OutlinedButton(
                onPressed: _busy ? null : _markFailed,
                style: OutlinedButton.styleFrom(foregroundColor: AppTheme.danger),
                child: const Text('Mark as Failed'),
              ),
            ),
          ],
        );
      default:
        return const SizedBox.shrink();
    }
  }
}
