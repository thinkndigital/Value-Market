import 'package:value_market_seller/commons/widgets/customTextButton.dart';
import 'package:value_market_seller/commons/blocs/userDetailsCubit.dart';
import 'package:value_market_seller/commons/widgets/safeAreaWithBottomPadding.dart';
import 'package:value_market_seller/features/profile/wallet/blocs/transactionCubit.dart';
import 'package:value_market_seller/features/profile/wallet/models/transaction.dart';
import 'package:value_market_seller/commons/widgets/customCircularProgressIndicator.dart';
import 'package:value_market_seller/commons/widgets/errorScreen.dart';
import 'package:value_market_seller/features/profile/wallet/widgets/transactionInfoContainer.dart';
import 'package:value_market_seller/utils/designConfig.dart';
import 'package:value_market_seller/core/localization/labelKeys.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:get/get.dart';

class TransactionScreen extends StatefulWidget {
  String? walletType;
  TransactionScreen({Key? key, this.walletType}) : super(key: key);
  static GlobalKey<TransactionScreenState> withdrawScreenKey =
      GlobalKey<TransactionScreenState>();
  static Widget getRouteInstance() {
    Map<String, dynamic> arguments = Get.arguments as Map<String, dynamic>;
    return BlocProvider(
      create: (context) => TransactionCubit(),
      child: TransactionScreen(
        walletType: arguments['walletType'],
      ),
    );
  }

  @override
  TransactionScreenState createState() => TransactionScreenState();
}

class TransactionScreenState extends State<TransactionScreen>
    with AutomaticKeepAliveClientMixin<TransactionScreen> {
  @override
  bool get wantKeepAlive => true;
  @override
  void initState() {
    super.initState();
    Future.delayed(Duration.zero, () {
      getTransactions();
    });
  }

  getTransactions() {
    context.read<TransactionCubit>().getTransaction(
        userId: context.read<UserDetailsCubit>().getUserId(),
        type: widget.walletType);
  }

  void loadMoreTransactions() {
    context.read<TransactionCubit>().loadMore(
        userId: context.read<UserDetailsCubit>().getUserId(),
        type: widget.walletType);
  }

  addItemInList(Transaction transaction) {
    List<Transaction> list =
        context.read<TransactionCubit>().getTransactionList();
    list.insert(0, transaction);
    context.read<TransactionCubit>().updateList(list);
  }

  @override
  Widget build(BuildContext context) {
    super.build(context);
    return Scaffold(
      body: SafeAreaWithBottomPadding(
        child: BlocBuilder<TransactionCubit, TransactionState>(
          builder: (context, state) {
            if (state is TransactionFetchSuccess) {
              return NotificationListener<ScrollUpdateNotification>(
                onNotification: (notification) {
                  if (notification.metrics.pixels ==
                      notification.metrics.maxScrollExtent) {
                    if (context.read<TransactionCubit>().hasMore()) {
                      loadMoreTransactions();
                    }
                  }
                  return true;
                },
                child: RefreshIndicator(
                  onRefresh: () async {
                    getTransactions();
                  },
                  child: ListView.separated(
                    padding:
                        const EdgeInsetsDirectional.symmetric(vertical: 12),
                    separatorBuilder: (context, index) =>
                        DesignConfig.smallHeightSizedBox,
                    itemCount: state.transactions.length,
                    itemBuilder: (context, index) {
                      if (context.read<TransactionCubit>().hasMore()) {
                        if (index == state.transactions.length - 1) {
                          if (context
                              .read<TransactionCubit>()
                              .fetchMoreError()) {
                            return Center(
                              child: CustomTextButton(
                                  buttonTextKey: retryKey,
                                  onTapButton: () {
                                    loadMoreTransactions();
                                  }),
                            );
                          }

                          return Center(
                            child: CustomCircularProgressIndicator(
                                indicatorColor:
                                    Theme.of(context).colorScheme.primary),
                          );
                        }
                      }
                      return TransactionInfoContainer(
                          transaction: state.transactions[index]);
                    },
                  ),
                ),
              );
            }
            if (state is TransactionFetchFailure) {
              return ErrorScreen(
                  text: state.errorMessage,
                  onPressed: getTransactions,
                  child: state is TransactionFetchInProgress
                      ? CustomCircularProgressIndicator(
                          indicatorColor: Theme.of(context).colorScheme.primary)
                      : null);
            }
            return Center(
              child: CustomCircularProgressIndicator(
                  indicatorColor: Theme.of(context).colorScheme.primary),
            );
          },
        ),
      ),
    );
  }
}
