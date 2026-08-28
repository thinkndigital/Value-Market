import 'package:eshopplus_seller/commons/widgets/customTextContainer.dart';
import 'package:eshopplus_seller/core/localization/labelKeys.dart';
import 'package:flutter/material.dart';
import 'package:fl_chart/fl_chart.dart';

class BarChartSample extends StatelessWidget {
  final List<double> totalSales;
  final List<double> totalOrders;
  final List<double> totalRevenue;
  final List<String> monthNames;

  BarChartSample({
    required this.totalSales,
    required this.totalOrders,
    required this.totalRevenue,
    required this.monthNames,
  });

  @override
  Widget build(BuildContext context) {
    double maxYValue = calculateMaxYValue(); // Calculate max value dynamically

    if (maxYValue != 0) {
      return Padding(
        padding: const EdgeInsetsDirectional.only(top: 60, end: 25),
        child: BarChart(
          BarChartData(
              maxY:
                  maxYValue, // Set the maximum value of the Y-axis dynamically
              minY: -1,
              alignment: BarChartAlignment.start,
              titlesData: FlTitlesData(
                topTitles:
                    const AxisTitles(sideTitles: SideTitles(showTitles: false)),
                rightTitles:
                    const AxisTitles(sideTitles: SideTitles(showTitles: false)),
                leftTitles: AxisTitles(
                  sideTitles: SideTitles(
                    showTitles: true,
                    getTitlesWidget: (double value, TitleMeta meta) {
                      return getYAxisLabel(
                          value, maxYValue); // Call dynamic label function
                    },
                    interval: maxYValue /
                        5, // Dynamically set the interval based on maxYValue
                    reservedSize: 50,
                  ),
                ),
                bottomTitles: AxisTitles(
                    sideTitles: SideTitles(
                  showTitles: true,
                  getTitlesWidget: (value, meta) {
                    return Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 4.0),
                      child: Transform.rotate(
                        angle:
                            -0.5, // Adjust the angle for cross line display (diagonal)
                        child: Text(
                          monthNames[value.toInt()],
                          style: const TextStyle(fontSize: 10),
                        ),
                      ),
                    );
                  },
                  reservedSize: 50,
                )),
              ),
              borderData: FlBorderData(
                show: false,
                border:
                    const Border.symmetric(horizontal: BorderSide(width: 1)),
              ),
              barTouchData: BarTouchData(enabled: true),
              barGroups: showingBarGroups(),
              gridData: FlGridData(
                horizontalInterval:
                    maxYValue / 5, // Dynamically set the horizontal interval
                show: true,
                drawHorizontalLine: true,
                drawVerticalLine: false,
              )),
        ),
      );
    }
    return const Center(
      child: CustomTextContainer(textKey: dataNotAvailableKey),
    );
  }

  Widget getYAxisLabel(double value, double maxY) {
    if (value == 0) {
      return const Text('0K');
    } else if (value == maxY / 5) {
      return Text('${(maxY * 0.2).round()}K');
    } else if (value == 2 * maxY / 5) {
      return Text('${(maxY * 0.4).round()}K');
    } else if (value == 3 * maxY / 5) {
      return Text('${(maxY * 0.6).round()}K');
    } else if (value == 4 * maxY / 5) {
      return Text('${(maxY * 0.8).round()}K');
    } else if (value == maxY) {
      return Text('${maxY.round()}K');
    } else {
      return const Text('');
    }
  }

  double calculateMaxYValue() {
    List<double> allValues = [];
    allValues.addAll(totalSales); // Replace with your actual data for sales
    allValues.addAll(totalOrders); // Replace with your actual data for orders
    allValues.addAll(totalRevenue); // Replace with your actual data for revenue

    double maxValue = allValues.reduce((a, b) => a > b ? a : b);

    // Round up the max value to the nearest 10K for cleaner axis display
    return (maxValue / 10000).ceil() *
        10000 /
        1000; // Convert to K and adjust for axis
  }

  List<BarChartGroupData> showingBarGroups() {
    return List.generate(totalSales.length, (i) {
      // Store the values and corresponding colors in a list
      List<Map<String, dynamic>> values = [
        {'value': totalRevenue[i], 'color': Colors.orange},
        {'value': totalOrders[i], 'color': Colors.green},
        {'value': totalSales[i], 'color': Colors.blue},
      ];
      // Sort the list based on the 'value' in descending order
      values.sort((a, b) => b['value'].compareTo(a['value']));
      return BarChartGroupData(
        x: i,
        groupVertically: true,
        barRods: values.map((item) {
          return BarChartRodData(
            toY: item['value'] / 1000, // Scale down for better visibility
            color: item['color'],
            width: 15,
            borderRadius: BorderRadius.circular(0),
          );
        }).toList(), // Convert the sorted list into BarChartRodData
      );
    });
  }
}
