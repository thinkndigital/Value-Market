// //create cubit to get paystack webview model from paystackwebview api
// import 'package:eshop_plus/ui/profile/transaction/models/paystackModel.dart';
// import 'package:eshop_plus/core/api/apiEndPoints.dart';
// import 'package:eshop_plus/core/api/apiService.dart';
// import 'package:eshop_plus/core/constants/hiveConstants.dart';
// import 'package:eshop_plus/core/localization/labelKeys.dart';
// import 'package:eshop_plus/ui/profile/transaction/repositories/transactionRepository.dart';
// import 'package:flutter_bloc/flutter_bloc.dart';

// abstract class PaystackWebviewState {}

// class PaystackWebviewInitial extends PaystackWebviewState {}

// class PaystackWebviewFetchInProgress extends PaystackWebviewState {}

// class PaystackWebviewFetchSuccess extends PaystackWebviewState {
//   final PaystackModel paystackModel;
//   PaystackWebviewFetchSuccess({required this.paystackModel});
// }

// class PaystackWebviewFetchFailure extends PaystackWebviewState {
//   final String errorMessage;
//   PaystackWebviewFetchFailure(this.errorMessage);
// }

// class PaystackWebviewCubit extends Cubit<PaystackWebviewState> {
//   final TransactionRepository _transactionRepository = TransactionRepository();

//   PaystackWebviewCubit() : super(PaystackWebviewInitial());

//   void getPaystackWebviewModel(final double amount) {
//     emit(PaystackWebviewFetchInProgress());
//     _transactionRepository
//         .getPaystackWebviewModel(amount)
//         .then(
//             (value) => emit(PaystackWebviewFetchSuccess(paystackModel: value)))
//         .catchError((e) {
//       emit(PaystackWebviewFetchFailure(e.toString()));
//     });
//   }
// }
