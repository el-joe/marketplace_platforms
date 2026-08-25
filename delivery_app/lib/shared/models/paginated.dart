class Paginated<T> {
  final List<T> items;
  final int currentPage;
  final int lastPage;
  final int total;

  Paginated({
    required this.items,
    required this.currentPage,
    required this.lastPage,
    this.total = 0,
  });

  bool get hasMore => currentPage < lastPage;

  factory Paginated.fromJson(
    Map<String, dynamic> json,
    T Function(Map<String, dynamic>) fromJson, {
    String itemsKey = 'items',
  }) {
    final rawItems = (json[itemsKey] ?? json['data'] ?? []) as List;
    final meta = (json['meta'] ?? {}) as Map<String, dynamic>;
    return Paginated<T>(
      items: rawItems.map((e) => fromJson(e as Map<String, dynamic>)).toList(),
      currentPage: (meta['current_page'] as num?)?.toInt() ?? 1,
      lastPage: (meta['last_page'] as num?)?.toInt() ?? 1,
      total: (meta['total'] as num?)?.toInt() ?? rawItems.length,
    );
  }
}
