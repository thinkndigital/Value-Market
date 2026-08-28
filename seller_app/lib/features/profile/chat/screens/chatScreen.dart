import 'package:dio/dio.dart';
import 'package:eshopplus_seller/core/constants/themeConstants.dart';
import 'package:eshopplus_seller/features/auth/repositories/authRepository.dart';
import 'package:eshopplus_seller/main.dart';
import 'package:eshopplus_seller/features/profile/chat/blocs/getMessageCubit.dart';
import 'package:eshopplus_seller/features/profile/chat/blocs/sendMessageCubit.dart';
import 'package:eshopplus_seller/commons/blocs/settingsAndLanguagesCubit.dart';
import 'package:eshopplus_seller/features/profile/chat/models/chatMessage.dart';

import 'package:eshopplus_seller/features/profile/chat/repositories/chatRepository.dart';
import 'package:eshopplus_seller/features/mainScreen.dart';
import 'package:eshopplus_seller/features/profile/chat/widgets/chatController.dart';
import 'package:eshopplus_seller/features/profile/chat/widgets/messageItem.dart';
import 'package:eshopplus_seller/features/profile/chat/widgets/pusherClient.dart';

import 'package:eshopplus_seller/commons/widgets/customAppbar.dart';
import 'package:eshopplus_seller/commons/widgets/customBottomButtonContainer.dart';
import 'package:eshopplus_seller/commons/widgets/customCircularProgressIndicator.dart';
import 'package:eshopplus_seller/commons/widgets/customDefaultContainer.dart';
import 'package:eshopplus_seller/commons/widgets/customTextButton.dart';
import 'package:eshopplus_seller/core/api/apiEndPoints.dart';

import 'package:eshopplus_seller/utils/designConfig.dart';
import 'package:eshopplus_seller/core/localization/labelKeys.dart';
import 'package:eshopplus_seller/utils/utils.dart';
import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:get/get.dart' as getX;
import 'package:get/route_manager.dart';
import 'package:pusher_channels_flutter/pusher_channels_flutter.dart';

class ChatScreen extends StatefulWidget {
  final int id;
  final String? userName;
  const ChatScreen({Key? key, required this.id, this.userName})
      : super(key: key);
  static Widget getRouteInstance() => MultiBlocProvider(
        providers: [
          BlocProvider(
            create: (context) => SendMessageCubit(),
          ),
          BlocProvider(
            create: (context) => GetMessageCubit(),
          ),
        ],
        child: ChatScreen(
          id: Get.arguments['id'],
          userName: Get.arguments['userName'],
        ),
      );

  @override
  _ChatScreenState createState() => _ChatScreenState();
}

// Initialize Pusher using your credentials.
// Subscribe to channels and bind events.
// Send messages using the trigger method.
// Listen to messages and update the UI.

final PusherService pusherService = PusherService();
PusherChannel? pusherChannel;

class _ChatScreenState extends State<ChatScreen> {
  List<ChatMessage> _messages = [];
  int totalMessages = 0;
  PlatformFile? _selectedfile;
  MultipartFile? file;
  final TextEditingController _controller = TextEditingController();
  final ChatController chatController = getX.Get.put(ChatController());
  late String channelName;
  @override
  void initState() {
    super.initState();

    currentChatUserId = widget.id.toString();

    initPusher();

    listenToMessages();
  }

  initPusher() async {
    Future.delayed(Duration.zero, () {
      channelName =
          context.read<SettingsAndLanguagesCubit>().getPusherChannerName();
      pusherService.initPusher(context, context.read<GetMessageCubit>());
    });
  }

  void listenToMessages() async {
    fetchMessages();
  }

  fetchMessages() {
    context.read<GetMessageCubit>().getMessages(params: {
      ApiURL.idApiKey: widget.id.toString(),
    });
  }

  loadMoreMessages() {
    context.read<GetMessageCubit>().loadMore(params: {
      ApiURL.idApiKey: widget.id.toString(),
    });
  }

  @override
  void dispose() {
    if (mounted) {
      getMessageCubit.updateUnreadCount(getContactsCubit, widget.id, _messages,
          replaceList: true);
    }

    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return BlocConsumer<SendMessageCubit, SendMessageState>(
        listener: (context, state) {
      if (state is SendMessageSuccess) {
        _controller.clear();
        _selectedfile = null;
        setState(() {
          _messages.add(state.chatMessage);
        });
      }
      if (state is SendMessageFailure) {
        Utils.showSnackBar( message: state.errorMessage);
      }
    }, builder: (context, sendMessagestate) {
      return BlocConsumer<GetMessageCubit, GetMessageState>(
          listener: (context, state) {
        if (state is GetMessageSuccess) {
          setState(() {
            _messages = state.messages.reversed.toList();
            totalMessages = state.total;
          });
          try {
            ChatRepository().makeSeenMessage(params: {
              ApiURL.idApiKey: widget.id.toString(),
            });
            _messages.forEach((element) {
              element.seen = 1;
            });
          } catch (e) {}
        }
      }, builder: (context, state) {
        return Scaffold(
            appBar: CustomAppbar(titleKey: widget.userName ?? chatKey),
            bottomNavigationBar: _buildMessageInput(sendMessagestate),
            body: state is GetMessageInProgress
                ? CustomCircularProgressIndicator(
                    indicatorColor: Theme.of(context).colorScheme.primary)
                : NotificationListener<ScrollUpdateNotification>(
                    onNotification: (notification) {
                      if (notification.metrics.pixels ==
                          notification.metrics.maxScrollExtent) {
                        if (context.read<GetMessageCubit>().hasMore()) {
                          loadMoreMessages();
                        }
                      }
                      return true;
                    },
                    child: ListView.builder(
                      reverse: true,
                      physics: const AlwaysScrollableScrollPhysics(),
                      itemCount: _messages.length,
                      padding: const EdgeInsetsDirectional.symmetric(
                          horizontal: appContentHorizontalPadding,
                          vertical: 12),
                      itemBuilder: (context, index) {
                        if (context.read<GetMessageCubit>().hasMore()) {
                          if (index == _messages.length - 1) {
                            if (context
                                .read<GetMessageCubit>()
                                .fetchMoreError()) {
                              return Center(
                                child: CustomTextButton(
                                    buttonTextKey: retryKey,
                                    onTapButton: () {
                                      loadMoreMessages();
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
                        final reversedIndex = _messages.length - 1 - index;
                        final message = _messages[reversedIndex];
                        final bool isAdmin =
                            message.fromId != AuthRepository.getUserId();
                        // Check if it's the first message from Customer Care or of the same message as previous
                        bool showLabel = isAdmin &&
                            (reversedIndex == 0 ||
                                !(_messages[reversedIndex - 1].fromId !=
                                    AuthRepository.getUserId()));
                        return MessageItem(
                            message: message, showLabel: showLabel);
                      },
                    ),
                  ));
      });
    });
  }

  bool isSameUser(int index, ChatMessage message, List<ChatMessage> messages) {
    bool isSameUser = true;
    if (index == 0) {
      isSameUser = false;
    } else {
      isSameUser = message.fromId == messages[index - 1].fromId;
    }
    return isSameUser;
  }

  Widget _buildMessageInput(SendMessageState state) {
    return Padding(
      padding:
          EdgeInsets.only(bottom: MediaQuery.of(context).viewInsets.bottom),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.end,
        mainAxisSize: MainAxisSize.min,
        children: [
          if (_selectedfile != null) attachmentItem(),
          CustomBottomButtonContainer(
              child: Row(
            children: <Widget>[
              // Left-side attachment button
              GestureDetector(
                onTap: pickFile,
                child: Container(
                  padding: const EdgeInsets.all(8),
                  decoration: BoxDecoration(
                      color: Theme.of(context).colorScheme.primary,
                      shape: BoxShape.circle),
                  child: Icon(
                    Icons.attach_file,
                    color: Theme.of(context).colorScheme.onPrimary,
                  ),
                ),
              ),
              DesignConfig.defaultWidthSizedBox,
              // Text field for message input
              Expanded(
                child: TextFormField(
                  controller: _controller,
                  textInputAction: TextInputAction.done,
                  maxLines: null,
                  decoration: InputDecoration(
                    hintText: context
                        .read<SettingsAndLanguagesCubit>()
                        .getTranslatedValue(labelKey: writeMessageKey),
                    helperStyle: Theme.of(context)
                        .textTheme
                        .bodyLarge!
                        .copyWith(
                            color: Theme.of(context)
                                .colorScheme
                                .secondary
                                .withValues(alpha: 0.8)),
                    border: InputBorder.none,
                    enabledBorder: InputBorder.none,
                    focusedBorder: InputBorder.none,
                  ),
                ),
              ),
              // Right-side send button
              GestureDetector(
                onTap: state is! SendMessageInProgress ? _sendMessage : null,
                child: Container(
                  padding: const EdgeInsets.all(8),
                  decoration: BoxDecoration(
                      color: Theme.of(context).colorScheme.primary,
                      shape: BoxShape.circle),
                  child: state is SendMessageInProgress
                      ? const FittedBox(
                          child: CustomCircularProgressIndicator())
                      : Icon(
                          Icons.send_outlined,
                          color: Theme.of(context).colorScheme.onPrimary,
                        ),
                ),
              ),
            ],
          )),
        ],
      ),
    );
  }

  attachmentItem() {
    return CustomDefaultContainer(
        child: Row(
      children: <Widget>[
        const Icon(
          Icons.file_copy_outlined,
          size: 30,
        ),
        Expanded(child: Text(_selectedfile!.name)),
        const Spacer(),
        IconButton(
          icon: Icon(Icons.cancel_outlined,
              size: 30, color: Theme.of(context).colorScheme.primary),
          onPressed: () {
            setState(() {
              _selectedfile = null;
            });
          },
        )
      ],
    ));
  }

  void pickFile() async {
    FilePickerResult? result = await FilePicker.platform.pickFiles(
      type: FileType.any, // Allow any type of file
    );

    if (result != null) {
      _selectedfile = result.files.first;
      setState(() {});
    }
  }

  void _sendMessage() {
    if (_controller.text.trim().isEmpty && _selectedfile == null) return;

    sendMessage(_controller.text);
  }

  void sendMessage(String text) async {
    if (_selectedfile != null) {
      file = await MultipartFile.fromFile(
        _selectedfile!.path!,
      );
    }

    context.read<SendMessageCubit>().sendMessage(params: {
      ApiURL.fromIdApiKey: AuthRepository.getUserId(),
      ApiURL.idApiKey: widget.id.toString(),
      ApiURL.messageApiKey: text,
      if (file != null) ApiURL.fileApiKey: file,
    });
  }
}
