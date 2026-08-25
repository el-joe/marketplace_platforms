import 'package:flutter/material.dart';

import 'claims_tab.dart';
import 'cod_settlements_tab.dart';
import 'earnings_tab.dart';
import 'orders_tab.dart';
import 'payouts_tab.dart';
import 'performance_tab.dart';

class ReportsScreen extends StatelessWidget {
  const ReportsScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return DefaultTabController(
      length: 6,
      child: Scaffold(
        appBar: AppBar(
          title: const Text('Reports'),
          bottom: const TabBar(
            isScrollable: true,
            tabs: [
              Tab(text: 'Orders'),
              Tab(text: 'Earnings'),
              Tab(text: 'Payouts'),
              Tab(text: 'COD Settlements'),
              Tab(text: 'Performance'),
              Tab(text: 'Claims'),
            ],
          ),
        ),
        body: const TabBarView(
          children: [
            OrdersTab(),
            EarningsTab(),
            PayoutsTab(),
            CodSettlementsTab(),
            PerformanceTab(),
            ClaimsTab(),
          ],
        ),
      ),
    );
  }
}
