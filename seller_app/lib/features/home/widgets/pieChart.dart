import 'package:flutter/material.dart';
import 'package:fl_chart/fl_chart.dart';
import 'package:flutter/widgets.dart';

class DonutChart extends StatelessWidget {
  final List<int> totalSold;
  final List<String> categoryNames;

  DonutChart({required this.totalSold, required this.categoryNames});

  @override
  Widget build(BuildContext context) {
    return PieChart(
      PieChartData(
        sections: showingSections(),
        centerSpaceRadius: 40, // This creates the donut hole effect
        sectionsSpace: 2,
        borderData: FlBorderData(show: false),
      
      ),
    );
  }

  List<PieChartSectionData> showingSections() {
    return List.generate(totalSold.length, (i) {
      final double radius = 50;
      final Color color = getColorForIndex(i);

      return PieChartSectionData(
        color: color,
        value: totalSold[i].toDouble(),
        radius: radius,
        // Remove title inside the chart sections
        title: '',
      );
    });
  }

  static Color getColorForIndex(int index) {
    // Return different colors for each category.
    switch (index) {
      case 0:
        return const Color(0xFF1A9BFB);
      case 1:
        return const Color(0xFFFEB019);
      case 2:
        return const Color(0xFF00E396);
      case 3:
        return const Color(0xFFFF546D);
      default:
        return const Color(0xFF8B74D7);
    }
  }
}

class LegendItem extends StatelessWidget {
  final Color color;
  final String text;

  LegendItem({required this.color, required this.text});

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Container(
          width: 16,
          height: 16,
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            color: color,
          ),
        ),
        SizedBox(width: 5),
        Flexible(
          child: Text(
            text,
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
          ),
        ),
      ],
    );
  }
}
