import 'package:eshopplus_seller/core/api/apiEndPoints.dart';
import 'package:eshopplus_seller/core/api/apiService.dart';
import 'package:eshopplus_seller/features/home/blocs/mostSellingCategory.dart';
import 'package:eshopplus_seller/features/home/blocs/overviewDataCubit.dart';
import 'package:eshopplus_seller/utils/utils.dart';

class SalesRepository {
  Future getSales(Map<String, dynamic> params) async {
    try {
      final result = await Api.get(
          url: ApiURL.getSalesList,
          useAuthToken: true,
          queryParameters: params);

      return result;
    } catch (e) {
      Utils.throwApiException(e);
    }
  }

  Future<
      ({
        String totalBalance,
        String totalSales,
        String totalOrders,
        String totalProducts,
        String lowStockProducts,
        String totalCommissionAmount,
      })> getTotalData({
    required Map<String, dynamic> params,
  }) async {
    try {
      final result = await Api.get(
          url: ApiURL.getTotalData,
          useAuthToken: true,
          queryParameters: params);
      var data = result[ApiURL.dataKey];
      return (
        totalBalance: (data['total_balance'] ?? 0).toString(),
        totalSales: (data['total_sales'] ?? 0).toString(),
        totalOrders: (data['total_orders'] ?? 0).toString(),
        totalProducts: (data['total_products'] ?? 0).toString(),
        lowStockProducts: (data['low_stock_products'] ?? 0).toString(),
        totalCommissionAmount:
            (data['total_commission_amount'] ?? 0).toString(),
      );
    } catch (e) {
      return Utils.throwApiException(e);
    }
  }

  Future<
      ({
        MostSellingCategory monthly,
        MostSellingCategory yearly,
        MostSellingCategory weekly,
      })> getMostSellingCategory({
    required Map<String, dynamic> params,
  }) async {
    try {
      final result = await Api.get(
          url: ApiURL.mostSellingCategories,
          useAuthToken: true,
          queryParameters: params);

      return (
        monthly: MostSellingCategory.fromJson(
            Map.from(result['most_selling_categories']['monthly'] ?? {})),
        yearly: MostSellingCategory.fromJson(
            Map.from(result['most_selling_categories']['yearly'] ?? {})),
        weekly: MostSellingCategory.fromJson(
            Map.from(result['most_selling_categories']['weekly'] ?? {})),
      );
    } catch (e) {
      return Utils.throwApiException(e);
    }
  }

  Future<
      ({
        StatisticsData monthly,
        StatisticsData weekly,
        StatisticsData daily,
      })> getOverviewData({
    required Map<String, dynamic> params,
  }) async {
    try {
      final result = await Api.get(
          url: ApiURL.getOverviewStatistic,
          useAuthToken: true,
          queryParameters: params);
      final monthlyData = result[ApiURL.dataKey]['monthly'];
      final weeklyData = result[ApiURL.dataKey]['weekly'];
      final dailyData = result[ApiURL.dataKey]['today'];
      return (
        monthly: StatisticsData(
            sales: List<double>.from(
                monthlyData['total_sale'].map((e) => (e as num).toDouble())),
            orders: List<double>.from(
                monthlyData['total_orders'].map((e) => (e as num).toDouble())),
            revenue: List<double>.from(
                monthlyData['total_revenue'].map((e) => (e as num).toDouble())),
            names: List<String>.from(monthlyData['month_name'])),
        weekly: StatisticsData(
            sales: List<double>.from(
                weeklyData['total_sale'].map((e) => (e as num).toDouble())),
            orders: List<double>.from(
                weeklyData['total_orders'].map((e) => (e as num).toDouble())),
            revenue: List<double>.from(
                weeklyData['total_revenue'].map((e) => (e as num).toDouble())),
            names: List<String>.from(weeklyData['day'])),
        daily: StatisticsData(
            sales: List<double>.from(
                [dailyData['total_sale']].map((e) => (e as num).toDouble())),
            orders: List<double>.from(
                [dailyData['total_orders']].map((e) => (e as num).toDouble())),
            revenue: List<double>.from(
                [dailyData['total_revenue']].map((e) => (e as num).toDouble())),
            names: List<String>.from([dailyData['day']])),
      );
    } catch (e) {
      return Utils.throwApiException(e);
    }
  }
}
