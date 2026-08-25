import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/api_exception.dart';
import '../../core/theme/app_theme.dart';
import '../../shared/widgets/empty_state.dart';
import '../../shared/widgets/error_view.dart';
import '../../shared/widgets/loading_view.dart';
import '../../shared/widgets/p_card.dart';
import '../../shared/widgets/status_chip.dart';
import 'profile_provider.dart';

class DocumentsScreen extends ConsumerWidget {
  const DocumentsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final documentsAsync = ref.watch(profileDocumentsProvider);

    return Scaffold(
      appBar: AppBar(title: const Text('Documents')),
      body: documentsAsync.when(
        loading: () => const LoadingView(),
        error: (e, _) => ErrorView(
          message: e is ApiException ? e.message : 'Failed to load documents.',
          onRetry: () => ref.invalidate(profileDocumentsProvider),
        ),
        data: (documents) => RefreshIndicator(
          onRefresh: () async => ref.invalidate(profileDocumentsProvider),
          child: documents.isEmpty
              ? ListView(children: const [
                  SizedBox(height: 120),
                  EmptyState(message: 'No documents on file.', icon: Icons.badge_outlined),
                ])
              : ListView.separated(
                  padding: const EdgeInsets.all(16),
                  itemCount: documents.length,
                  separatorBuilder: (_, __) => const SizedBox(height: 10),
                  itemBuilder: (context, index) {
                    final d = documents[index];
                    return PCard(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            children: [
                              Expanded(
                                  child: Text('${d['document_type_name'] ?? d['document_type'] ?? '-'}',
                                      style: const TextStyle(fontWeight: FontWeight.w600))),
                              if (d['status'] != null) StatusChip(status: '${d['status']}'),
                            ],
                          ),
                          if (d['status'] == 'rejected' && d['rejection_reason'] != null) ...[
                            const SizedBox(height: 8),
                            Text('${d['rejection_reason']}', style: const TextStyle(color: AppTheme.error)),
                          ],
                          if (d['expires_at'] != null) ...[
                            const SizedBox(height: 6),
                            Text('Expires: ${d['expires_at']}', style: const TextStyle(color: AppTheme.textSecondary, fontSize: 12)),
                          ],
                        ],
                      ),
                    );
                  },
                ),
        ),
      ),
    );
  }
}
