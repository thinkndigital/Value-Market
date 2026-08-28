import 'package:eshop_plus/core/constants/themeConstants.dart';
import 'package:eshop_plus/core/routes/routes.dart';
import 'package:eshop_plus/ui/profile/customerSupport/blocs/getTicketCubit.dart';
import 'package:eshop_plus/commons/blocs/settingsAndLanguagesCubit.dart';
import 'package:eshop_plus/ui/profile/customerSupport/models/ticket.dart';
import 'package:eshop_plus/commons/widgets/customRoundedButton.dart';
import 'package:eshop_plus/commons/widgets/customStatusContainer.dart';
import 'package:eshop_plus/commons/widgets/customTextContainer.dart';
import 'package:eshop_plus/utils/designConfig.dart';
import 'package:eshop_plus/core/localization/labelKeys.dart';
import 'package:eshop_plus/utils/utils.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

class TicketItemContainer extends StatelessWidget {
  final Ticket ticket;
  final TicketCubit ticketCubit;
  const TicketItemContainer(this.ticket, this.ticketCubit, {Key? key})
      : super(key: key);

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsetsDirectional.symmetric(
          vertical: appContentHorizontalPadding),
      decoration: BoxDecoration(
          color: Theme.of(context).colorScheme.primaryContainer,
          borderRadius: BorderRadius.circular(8)),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Padding(
            padding: const EdgeInsetsDirectional.symmetric(
                horizontal: appContentHorizontalPadding),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                CustomTextContainer(
                  textKey: 'ID: #${ticket.id}',
                  style: Theme.of(context)
                      .textTheme
                      .titleMedium!
                      .copyWith(color: Theme.of(context).colorScheme.primary),
                ),
                if (ticket.status != null)
                  CustomStatusContainer(
                      getValueList: Utils.getTicketStatusTextAndColor,
                      status: ticket.status.toString())
              ],
            ),
          ),
          const Divider(
            thickness: 0.5,
          ),
          Padding(
            padding: const EdgeInsetsDirectional.symmetric(
                horizontal: appContentHorizontalPadding),
            child: Column(
              children: <Widget>[
                buildLabelAndValue(context, typeKey, ticket.ticketType ?? ''),
                buildLabelAndValue(context, subjectKey, ticket.subject ?? ''),
                buildLabelAndValue(
                    context, descriptionKey, ticket.description.toString()),
                buildLabelAndValue(
                    context, dateKey, ticket.createdAt.toString()),
                DesignConfig.defaultHeightSizedBox,
                Row(
                  children: [
                    CustomRoundedButton(
                        height: 28,
                        // widthPercentage: 0.2,
                        horizontalPadding: 16,
                        buttonTitle: editKey,
                        showBorder: true,
                        backgroundColor:
                            Theme.of(context).colorScheme.onPrimary,
                        borderColor: Theme.of(context).hintColor,
                        style: const TextStyle().copyWith(
                          color: Theme.of(context).colorScheme.secondary,
                        ),
                        onTap: () => Utils.navigateToScreen(
                                context, Routes.askQueryScreen,
                                arguments: {
                                  'ticketCubit': ticketCubit,
                                  'ticket': ticket,
                                })),
                    //will not show chat button for pending & closed tickets
                    if (![1, 4].contains(ticket.status)) ...[
                      DesignConfig.defaultWidthSizedBox,
                      CustomRoundedButton(
                          height: 28,
                          horizontalPadding: 16,
                          buttonTitle: chatKey,
                          showBorder: false,
                          style: const TextStyle().copyWith(
                            color: Theme.of(context).colorScheme.onPrimary,
                          ),
                          onTap: () => Utils.navigateToScreen(
                                  context, Routes.chatScreen,
                                  arguments: {
                                    'id': ticket.id,
                                    'isTicketChatScreen': true,
                                  })),
                    ]
                  ],
                )
              ],
            ),
          )
        ],
      ),
    );
  }

  buildLabelAndValue(BuildContext context, String title, String value) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text.rich(
          TextSpan(
            children: [
              TextSpan(
                text: context
                    .read<SettingsAndLanguagesCubit>()
                    .getTranslatedValue(labelKey: title),
                style: Theme.of(context).textTheme.titleSmall,
              ),
              TextSpan(
                text: ' : ',
                style: Theme.of(context).textTheme.titleSmall,
              ),
            ],
          ),
        ),
        Expanded(
          child: CustomTextContainer(
            textKey: value,
            style: Theme.of(context).textTheme.bodyMedium!.copyWith(
                color: Theme.of(context)
                    .colorScheme
                    .secondary
                    .withValues(alpha: 0.8)),
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
          ),
        ),
      ],
    );
  }
}
