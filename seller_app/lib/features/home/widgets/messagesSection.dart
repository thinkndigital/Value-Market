import 'package:eshopplus_seller/commons/widgets/customDefaultContainer.dart';
import 'package:eshopplus_seller/commons/widgets/customTextButton.dart';
import 'package:eshopplus_seller/core/api/apiEndPoints.dart';
import 'package:eshopplus_seller/core/routes/routes.dart';
import 'package:eshopplus_seller/features/auth/repositories/authRepository.dart';
import 'package:eshopplus_seller/features/profile/chat/blocs/getContactsCubit.dart';
import 'package:eshopplus_seller/features/profile/chat/blocs/getMessageCubit.dart';
import 'package:eshopplus_seller/features/profile/chat/models/chatMessage.dart';
import 'package:eshopplus_seller/commons/models/userDetails.dart';
import 'package:eshopplus_seller/features/profile/chat/repositories/chatRepository.dart';

import 'package:eshopplus_seller/commons/widgets/customTextContainer.dart';
import 'package:eshopplus_seller/utils/designConfig.dart';
import 'package:eshopplus_seller/core/localization/labelKeys.dart';
import 'package:eshopplus_seller/utils/utils.dart';

import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

class MessagesSection extends StatefulWidget {
  const MessagesSection({Key? key}) : super(key: key);

  @override
  _MessagesSectionState createState() => _MessagesSectionState();
}

class _MessagesSectionState extends State<MessagesSection>
    with AutomaticKeepAliveClientMixin {
  @override
  bool get wantKeepAlive => true;
  @override
  void initState() {
    super.initState();
    Future.delayed(Duration.zero, () {
      context.read<GetContactsCubit>().getContactss();
    });
  }

  @override
  Widget build(BuildContext context) {
    super.build(context);
    return Column(
      children: [
        DesignConfig.smallHeightSizedBox,
        CustomDefaultContainer(
          child: Column(
            children: [
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  CustomTextContainer(
                    textKey: newMessagesKey,
                    style: Theme.of(context).textTheme.bodyLarge,
                  ),
                  CustomTextButton(
                      textStyle: Theme.of(context)
                          .textTheme
                          .bodyMedium!
                          .copyWith(
                              color: Theme.of(context).colorScheme.primary),
                      buttonTextKey: viewAllKey,
                      onTapButton: () => Utils.navigateToScreen(
                          context, Routes.contactListScreen))
                ],
              ),
              BlocBuilder<GetMessageCubit, GetMessageState>(
                builder: (context, state) {
                  return BlocConsumer<GetContactsCubit, GetContactsState>(
                    listener: (context, state) {
                      if (state is GetContactsSuccess) {
                        //filter only admin and customers
                        state.users.retainWhere(
                            (element) => [1, 2, 5].contains(element.roleId));

                        state.users.forEach((user) async {
                          try {
                            final result =
                                await ChatRepository().getMessages(params: {
                              ApiURL.idApiKey: user.id.toString(),
                            });
                            user.messages = result.messages;
                            user.unreadCount = result.messages
                                .where((element) =>
                                    element.toId ==
                                        AuthRepository.getUserId() &&
                                    element.seen == 0)
                                .length;
                          } catch (e) {
                            user.messages = [];
                          }
                          if (mounted) setState(() {});
                        });
                      }
                    },
                    builder: (context, state) {
                      if (state is GetContactsSuccess) {
                        return BlocListener<GetMessageCubit, GetMessageState>(
                          listener: (context, messageState) async {
                            if (messageState is GetMessageSuccess) {
                              int index = state.users.indexWhere((element) =>
                                  element.id ==
                                      messageState.messages.first.toId ||
                                  element.id ==
                                      messageState.messages.first.fromId);
                              if (index != -1) {
                                state.users[index].messages =
                                    messageState.messages;
                                state.users[index].unreadCount = messageState
                                    .messages
                                    .where((element) =>
                                        element.toId ==
                                            AuthRepository.getUserId() &&
                                        element.seen == 0)
                                    .length;
                              } else {}
                            }
                          },
                          child: ListView.separated(
                            scrollDirection: Axis.vertical,
                            separatorBuilder: (context, index) => Divider(
                              color: Theme.of(context)
                                  .inputDecorationTheme
                                  .iconColor!,
                              height: 0.3,
                            ),
                            itemCount:
                                state.users.length > 5 ? 5 : state.users.length,
                            shrinkWrap: true,
                            physics: const NeverScrollableScrollPhysics(),
                            itemBuilder: (context, index) {
                              UserDetails user = state.users[index];

                              if (user.messages != null &&
                                  user.messages!.isNotEmpty) {
                                return buildUserContainer(user,
                                    message: user.messages!.first,
                                    unreadCount: user.unreadCount ?? 0);
                              }

                              return buildUserContainer(user,
                                  unreadCount: user.unreadCount ?? 0);
                            },
                          ),
                        );
                      }
                      return const SizedBox();
                    },
                  );
                },
              ),
            ],
          ),
        ),
      ],
    );
  }

  Widget buildUserContainer(UserDetails user,
      {ChatMessage? message, int unreadCount = 0}) {
    return GestureDetector(
      onTap: () => Utils.navigateToScreen(context, Routes.chatScreen,
          arguments: {'id': user.id, 'userName': user.username}),
      child: CustomDefaultContainer(
          borderRadius: 8,
          child: Row(
            children: <Widget>[
              Utils.buildProfilePicture(
                context,
                48,
                user.image ?? '',
                outerBorderColor: Colors.transparent,
              ),
              const SizedBox(
                width: 12,
              ),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    CustomTextContainer(
                      textKey: user.username ?? '',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: Theme.of(context).textTheme.titleMedium,
                    ),
                    if (message != null)
                      Text(
                        message.body,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: Theme.of(context).textTheme.bodyMedium!.copyWith(
                            color: Theme.of(context)
                                .colorScheme
                                .secondary
                                .withValues(alpha: 0.8)),
                      ),
                  ],
                ),
              ),
              if (message != null)
                Column(
                  children: [
                    Text(
                      message.createdAt!,
                      style: Theme.of(context).textTheme.bodyMedium!.copyWith(
                          color: Theme.of(context)
                              .colorScheme
                              .secondary
                              .withValues(alpha: 0.67)),
                    ),
                    if (unreadCount > 0)
                      Badge(
                        backgroundColor: Theme.of(context).colorScheme.primary,
                        textColor: Theme.of(context).colorScheme.onPrimary,
                        isLabelVisible: true,
                        label: Text(
                            unreadCount > 10 ? '10+' : unreadCount.toString()),
                      )
                  ],
                ),
            ],
          )),
    );
  }
}
