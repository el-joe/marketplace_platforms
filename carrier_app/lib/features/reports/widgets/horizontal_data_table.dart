import 'package:flutter/material.dart';

/// Wraps a [DataTable] in horizontal scroll so wide report tables never
/// overflow the screen.
class HorizontalDataTable extends StatelessWidget {
  final List<DataColumn> columns;
  final List<DataRow> rows;

  const HorizontalDataTable({super.key, required this.columns, required this.rows});

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      child: DataTable(columns: columns, rows: rows),
    );
  }
}
