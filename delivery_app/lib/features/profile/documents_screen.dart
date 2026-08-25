import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:image_picker/image_picker.dart';

import '../../core/api/api_exception.dart';
import '../../core/theme/app_theme.dart';
import '../../shared/widgets/d_card.dart';
import '../../shared/widgets/error_view.dart';
import '../../shared/widgets/loading_view.dart';
import '../../shared/widgets/status_chip.dart';
import 'profile_provider.dart';

class DocumentsScreen extends ConsumerStatefulWidget {
  const DocumentsScreen({super.key});

  @override
  ConsumerState<DocumentsScreen> createState() => _DocumentsScreenState();
}

class _DocumentsScreenState extends ConsumerState<DocumentsScreen> {
  String? _uploadingType;

  Future<void> _reupload(String type) async {
    final picked = await ImagePicker().pickImage(source: ImageSource.gallery, imageQuality: 85);
    if (picked == null) return;
    setState(() => _uploadingType = type);
    try {
      await ref.read(profileRepositoryProvider).reuploadDocument(type, File(picked.path));
      ref.invalidate(documentsProvider);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Document submitted for review.')));
      }
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    } finally {
      if (mounted) setState(() => _uploadingType = null);
    }
  }

  @override
  Widget build(BuildContext context) {
    final documentsAsync = ref.watch(documentsProvider);

    return Scaffold(
      appBar: AppBar(title: const Text('Documents')),
      body: documentsAsync.when(
        loading: () => const LoadingView(),
        error: (e, _) => ErrorView(message: e is ApiException ? e.message : 'Failed to load documents.'),
        data: (documents) => ListView.separated(
          padding: const EdgeInsets.all(16),
          itemCount: documents.length,
          separatorBuilder: (_, __) => const SizedBox(height: 12),
          itemBuilder: (context, index) {
            final d = documents[index];
            final busy = _uploadingType == d.documentType;
            return DCard(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Expanded(
                        child: Text(d.documentType?.replaceAll('_', ' ') ?? '-',
                            style: const TextStyle(fontWeight: FontWeight.w600)),
                      ),
                      StatusChip(status: d.status ?? ''),
                    ],
                  ),
                  if (d.status == 'rejected' && d.rejectionReason != null) ...[
                    const SizedBox(height: 8),
                    Text(d.rejectionReason!, style: const TextStyle(color: AppTheme.danger)),
                  ],
                  const SizedBox(height: 12),
                  SizedBox(
                    width: double.infinity,
                    child: OutlinedButton.icon(
                      onPressed: busy || d.documentType == null ? null : () => _reupload(d.documentType!),
                      icon: busy
                          ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2))
                          : const Icon(Icons.upload_outlined),
                      label: const Text('Re-upload'),
                    ),
                  ),
                ],
              ),
            );
          },
        ),
      ),
    );
  }
}
