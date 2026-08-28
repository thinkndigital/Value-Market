import 'package:eshop_plus/core/constants/themeConstants.dart';
import 'package:eshop_plus/core/routes/routes.dart';
import 'package:eshop_plus/ui/profile/customerSupport/blocs/getTicketCubit.dart';
import 'package:eshop_plus/ui/profile/customerSupport/widgets/ticketItemContainer.dart';
import 'package:eshop_plus/commons/widgets/customAppbar.dart';
import 'package:eshop_plus/commons/widgets/customBottomButtonContainer.dart';
import 'package:eshop_plus/commons/widgets/customCircularProgressIndicator.dart';
import 'package:eshop_plus/commons/widgets/customDefaultContainer.dart';
import 'package:eshop_plus/commons/widgets/customRoundedButton.dart';
import 'package:eshop_plus/commons/widgets/customTextButton.dart';
import 'package:eshop_plus/commons/widgets/customTextContainer.dart';
import 'package:eshop_plus/commons/widgets/error_screen.dart';
import 'package:eshop_plus/utils/designConfig.dart';
import 'package:eshop_plus/core/localization/labelKeys.dart';
import 'package:eshop_plus/utils/utils.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

class CustomerSupportScreen extends StatefulWidget {
  const CustomerSupportScreen({Key? key}) : super(key: key);
  static Widget getRouteInstance() => BlocProvider(
        create: (context) => TicketCubit(),
        child: CustomerSupportScreen(
          key: customerSupportScreenKey,
        ),
      );
  @override
  _CustomerSupportScreenState createState() => _CustomerSupportScreenState();
}

final GlobalKey<_CustomerSupportScreenState> customerSupportScreenKey =
    GlobalKey<_CustomerSupportScreenState>();

class _CustomerSupportScreenState extends State<CustomerSupportScreen> {
  @override
  void initState() {
    super.initState();
    Future.delayed(Duration.zero, () {
      getTickets();
    });
  }

  getTickets() {
    context.read<TicketCubit>().getTickets();
  }

  void loadMoreTickets() {
    context.read<TicketCubit>().loadMore();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: const CustomAppbar(titleKey: customerSupportKey),
      bottomNavigationBar: CustomBottomButtonContainer(
        child: CustomRoundedButton(
          widthPercentage: 1.0,
          buttonTitle: askQueryKey,
          showBorder: false,
          onTap: () => Utils.navigateToScreen(context, Routes.askQueryScreen,
              arguments: {'ticketCubit': context.read<TicketCubit>()}),
        ),
      ),
      body: Padding(
        padding: const EdgeInsetsDirectional.symmetric(
            vertical: 12, horizontal: appContentHorizontalPadding),
        child: Column(
          children: <Widget>[
            buildChatContainer(),
            DesignConfig.defaultHeightSizedBox,
            buildTicketList()
          ],
        ),
      ),
    );
  }

  buildChatContainer() {
    return GestureDetector(
      onTap: () => Utils.navigateToScreen(context, Routes.userListScreen),
      child: CustomDefaultContainer(
          child: Row(
        children: <Widget>[
          Container(
            padding: const EdgeInsets.all(7),
            decoration: BoxDecoration(
              color:
                  Theme.of(context).colorScheme.primary.withValues(alpha: 0.10),
              borderRadius: BorderRadius.circular(8),
            ),
            width: 36,
            height: 36,
            child: Icon(
              Icons.chat_bubble_outline,
              color: Theme.of(context).colorScheme.primary,
            ),
          ),
          DesignConfig.smallWidthSizedBox,
          Expanded(
            child: CustomTextContainer(
              textKey: chatKey,
              style: Theme.of(context).textTheme.titleMedium!.copyWith(
                  color: Theme.of(context)
                      .colorScheme
                      .secondary
                      .withValues(alpha: 0.8)),
            ),
          ),
          Icon(
            Icons.arrow_forward_ios,
            color:
                Theme.of(context).colorScheme.secondary.withValues(alpha: 0.8),
          )
        ],
      )),
    );
  }

  buildTicketList() {
    return Expanded(
      child: RefreshIndicator(
        onRefresh: () async {
          getTickets();
        },
        child: BlocBuilder<TicketCubit, TicketState>(
          builder: (context, state) {
            if (state is TicketFetchSuccess) {
              return NotificationListener<ScrollUpdateNotification>(
                onNotification: (notification) {
                  if (notification.metrics.pixels >=
                      notification.metrics.maxScrollExtent) {
                    if (context.read<TicketCubit>().hasMore()) {
                      loadMoreTickets();
                    }
                  }
                  return true;
                },
                child: ListView.separated(
                  separatorBuilder: (context, index) =>
                      DesignConfig.smallHeightSizedBox,
                  shrinkWrap: true,
                  itemCount: state.tickets.length,
                  itemBuilder: (context, index) {
                    if (context.read<TicketCubit>().hasMore()) {
                      if (index == state.tickets.length - 1) {
                        if (context.read<TicketCubit>().fetchMoreError()) {
                          return Center(
                            child: CustomTextButton(
                                buttonTextKey: retryKey,
                                onTapButton: () {
                                  loadMoreTickets();
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
                    return TicketItemContainer(
                        state.tickets[index], context.read<TicketCubit>());
                  },
                ),
              );
            }
            if (state is TicketFetchFailure) {
              return ErrorScreen(
                  onPressed: getTickets,
                  text: state.errorMessage,
                  child: state is TicketFetchInProgress
                      ? CustomCircularProgressIndicator(
                          indicatorColor: Theme.of(context).colorScheme.primary,
                        )
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
