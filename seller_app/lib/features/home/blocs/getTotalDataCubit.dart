import 'package:eshopplus_seller/features/profile/salesReport/repositories/salesRepository.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

abstract class GetTotalDataState {}

class GetTotalDataInitial extends GetTotalDataState {}

class GetTotalDataFetchInProgress extends GetTotalDataState {}

class GetTotalDataFetchSuccess extends GetTotalDataState {
  final String totalBalance;
  final String totalSales;
  final String totalOrders;
  final String totalProducts;
  final String lowStockProducts;
  final String totalCommissionAmount;

  GetTotalDataFetchSuccess(
      {required this.totalBalance,
      required this.totalSales,
      required this.totalOrders,
      required this.totalProducts,
      required this.lowStockProducts,
      required this.totalCommissionAmount});
}

class GetTotalDataFetchFailure extends GetTotalDataState {
  final String errorMessage;

  GetTotalDataFetchFailure(this.errorMessage);
}

class GetTotalDataCubit extends Cubit<GetTotalDataState> {
  final SalesRepository salesRepository = SalesRepository();

  GetTotalDataCubit() : super(GetTotalDataInitial());

  void getTotalData({required Map<String, dynamic> params}) {
    emit(GetTotalDataFetchInProgress());
    salesRepository
        .getTotalData(params: params)
        .then((value) => emit(GetTotalDataFetchSuccess(
              totalBalance: value.totalBalance,
              totalSales: value.totalSales,
              totalOrders: value.totalOrders,
              totalProducts: value.totalProducts,
              lowStockProducts: value.lowStockProducts,
              totalCommissionAmount: value.totalCommissionAmount,
            )))
        .catchError((e) {
      emit(GetTotalDataFetchFailure(e.toString()));
    });
  }
}
