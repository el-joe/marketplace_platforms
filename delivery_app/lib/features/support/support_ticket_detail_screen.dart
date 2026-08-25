import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/api_exception.dart';
import '../../core/utils/date_formatter.dart';
import '../../shared/widgets/d_card.dart';
import '../../shared/widgets/error_view.dart';
import '../../shared/widgets/loading_view.dart';
import '../../shared/widgets/status_chip.dart';
import 'support_provider.dart';

class SupportTicketDetailScreen extends ConsumerStatefulWidget {
  final String ticketNumber;

  const SupportTicketDetailScreen({super.key, required this.ticketNumber});

  @override
  ConsumerState<SupportTicketDetailScreen> createState() => _SupportTicketDetailScreenState();
}

class _SupportTicketDetailScreenState extends ConsumerState<SupportTicketDetailScreen> {
  final _messageController = TextEditingController();
  bool _sending = false;

  @override
  void dispose() {
    _messageController.dispose();
    super.dispose();
  }

  Future<void> _send() async {
    final text = _messageController.text.trim();
    if (text.isEmpty) return;
    setState(() => _sending = true);
    try {
      await ref.read(ticketDetailProvider(widget.ticketNumber).notifier).sendMessage(text);
      _messageController.clear();
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  Future<void> _rate() async {
    int rating = 5;
    final controller = TextEditingController();
    final result = await showDialog<bool>(
      context: context,
      builder: (context) => StatefulBuilder(
        builder: (context, setDialogState) => AlertDialog(
          title: const Text('Rate this support experience'),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: List.generate(
                  5,
                  (i) => IconButton(
                    icon: Icon(i < rating ? Icons.star : Icons.star_border, color: Colors.amber),
                    onPressed: () => setDialogState(() => rating = i + 1),
                  ),
                ),
              ),
              TextField(controller: controller, decoration: const InputDecoration(labelText: 'Comment (optional)')),
            ],
          ),
          actions: [
            TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Cancel')),
            TextButton(onPressed: () => Navigator.pop(context, true), child: const Text('Submit')),
          ],
        ),
      ),
    );

    if (result == true && mounted) {
      await ref.read(ticketDetailProvider(widget.ticketNumber).notifier).rate(
            rating: rating,
            comment: controller.text.trim().isEmpty ? null : controller.text.trim(),
          );
    }
  }

  @override
  Widget build(BuildContext context) {
    final ticketAsync = ref.watch(ticketDetailProvider(widget.ticketNumber));

    return Scaffold(
      appBar: AppBar(title: Text(widget.ticketNumber)),
      body: ticketAsync.when(
        loading: () => const LoadingView(),
        error: (e, _) => ErrorView(message: e is ApiException ? e.message : 'Failed to load ticket.'),
        data: (ticket) => Column(
          children: [
            Expanded(
              child: ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  DCard(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            Expanded(child: Text(ticket.subject ?? '', style: const TextStyle(fontWeight: FontWeight.bold))),
                            StatusChip(status: ticket.status ?? ''),
                          ],
                        ),
                        const SizedBox(height: 4),
                        Text('Category: ${ticket.category ?? '-'} · Priority: ${ticket.priority ?? '-'}'),
                        if (ticket.status == 'resolved' && ticket.satisfactionRating == null) ...[
                          const SizedBox(height: 12),
                          OutlinedButton(onPressed: _rate, child: const Text('Rate this ticket')),
                        ],
                      ],
                    ),
                  ),
                  const SizedBox(height: 16),
                  ...ticket.messages.map((m) => Padding(
                        padding: const EdgeInsets.only(bottom: 12),
                        child: DCard(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(m.message ?? ''),
                              const SizedBox(height: 4),
                              Text(DateFormatter.dateTime(m.createdAt),
                                  style: TextStyle(color: Colors.grey.shade500, fontSize: 12)),
                            ],
                          ),
                        ),
                      )),
                ],
              ),
            ),
            SafeArea(
              child: Padding(
                padding: const EdgeInsets.all(12),
                child: Row(
                  children: [
                    Expanded(
                      child: TextField(
                        controller: _messageController,
                        decoration: const InputDecoration(hintText: 'Type a message...'),
                      ),
                    ),
                    const SizedBox(width: 8),
                    IconButton(
                      icon: _sending
                          ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2))
                          : const Icon(Icons.send),
                      onPressed: _sending ? null : _send,
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
