import 'package:value_market_delivery_boy/features/wallet/blocs/transactionCubit.dart';
import 'package:value_market_delivery_boy/features/wallet/widgets/transactionInfoContainer.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:get/get.dart';

import '../../../commons/blocs/userDetailsCubit.dart';
import '../models/transaction.dart';
import '../../../utils/designConfig.dart';
import '../../../core/localization/labelKeys.dart';
import '../../../commons/widgets/customCircularProgressIndicator.dart';
import '../../../commons/widgets/customTextButton.dart';
import '../../../commons/widgets/errorScreen.dart';

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
        key: TransactionScreen.withdrawScreenKey,
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

  addItemInList(Transaction transaction) {
    List<Transaction> list =
        context.read<TransactionCubit>().getTransactionList();
    list.insert(0, transaction);
    context.read<TransactionCubit>().updateList(list);
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

  @override
  Widget build(BuildContext context) {
    super.build(context);
    return Scaffold(
      body: BlocBuilder<TransactionCubit, TransactionState>(
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
              child: Padding(
                padding: const EdgeInsetsDirectional.symmetric(vertical: 12),
                child: RefreshIndicator(
                  onRefresh: () async {
                    getTransactions();
                  },
                  child: ListView.separated(
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
    );
  }
}
