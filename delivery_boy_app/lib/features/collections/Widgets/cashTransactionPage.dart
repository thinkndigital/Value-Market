import 'package:value_market_delivery_boy/features/collections/blocs/cashCollectionCubit.dart';
import 'package:value_market_delivery_boy/features/collections/Widgets/collectionInfoContainer.dart';
import 'package:value_market_delivery_boy/commons/widgets/customCircularProgressIndicator.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:get/get.dart';
import '../../../../utils/designConfig.dart';
import '../../../core/localization/labelKeys.dart';
import '../../../commons/widgets/customTextButton.dart';
import '../../../commons/widgets/errorScreen.dart';

class CashTransactionScreen extends StatefulWidget {
  final String? type;
  const CashTransactionScreen({super.key, this.type});

  static Widget getRouteInstance() {
    Map<String, dynamic> arguments = Get.arguments as Map<String, dynamic>;
    return BlocProvider(
      create: (context) => CashCollectionCubit(),
      child: CashTransactionScreen(
        type: arguments['type'],
      ),
    );
  }

  @override
  _CashTransactionScreenState createState() => _CashTransactionScreenState();
}

class _CashTransactionScreenState extends State<CashTransactionScreen>
    with AutomaticKeepAliveClientMixin<CashTransactionScreen> {
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
    context.read<CashCollectionCubit>().getTransaction(widget.type!);
  }

  void loadMoreTransactions() {
    context.read<CashCollectionCubit>().loadMore(widget.type!);
  }

  @override
  Widget build(BuildContext context) {
    super.build(context);
    return Scaffold(
      body: BlocBuilder<CashCollectionCubit, CashCollectionState>(
        builder: (context, state) {
          if (state is CashCollectionFetchSuccess) {
            return NotificationListener<ScrollUpdateNotification>(
              onNotification: (notification) {
                if (notification.metrics.pixels ==
                    notification.metrics.maxScrollExtent) {
                  if (context.read<CashCollectionCubit>().hasMore()) {
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
                      if (context.read<CashCollectionCubit>().hasMore()) {
                        if (index == state.transactions.length - 1) {
                          if (context
                              .read<CashCollectionCubit>()
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
                      return CollectionInfoContainer(
                          transaction: state.transactions[index]);
                    },
                  ),
                ),
              ),
            );
          }
          if (state is CashCollectionFetchFailure) {
            return ErrorScreen(
                text: state.errorMessage,
                onPressed: getTransactions,
                child: state is CashCollectionFetchProgress
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
