import 'package:eshopplus_seller/features/profile/salesReport/repositories/salesRepository.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

abstract class OverviewDataState {}

class OverviewDataInitial extends OverviewDataState {}

class OverviewDataProgress extends OverviewDataState {}

class OverviewDataFailure extends OverviewDataState {
  final String errorMessage;
  OverviewDataFailure(this.errorMessage);
}

class OverviewDataSuccess extends OverviewDataState {
  final StatisticsData monthlyStatistics;
  final StatisticsData weeklyStatistics;
  final StatisticsData dailyStatistics;
  OverviewDataSuccess(
      {required this.monthlyStatistics,
      required this.weeklyStatistics,
      required this.dailyStatistics});
}

class OverviewDataCubit extends Cubit<OverviewDataState> {
  final SalesRepository salesRepository = SalesRepository();
  OverviewDataCubit() : super(OverviewDataInitial());
  void getOverviewData({required Map<String, dynamic> params}) async {
    emit(OverviewDataProgress());

    try {
      final result = await salesRepository.getOverviewData(params: params);

      emit(OverviewDataSuccess(
          dailyStatistics: result.daily,
          monthlyStatistics: result.monthly,
          weeklyStatistics: result.weekly));
    } catch (e) {
      emit(OverviewDataFailure(e.toString()));
    }
  }
}

class StatisticsData {
  final List<double> sales;
  final List<double> orders;
  final List<double> revenue;
  final List<String> names;
  StatisticsData(
      {required this.sales,
      required this.orders,
      required this.revenue,
      required this.names});
}
