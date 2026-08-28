import 'package:dio/dio.dart';
import 'package:value_market_customer/core/constants/themeConstants.dart';
import 'package:value_market_customer/main.dart';
import 'package:value_market_customer/ui/profile/chat/blocs/getMessageCubit.dart';
import 'package:value_market_customer/ui/profile/chat/blocs/sendMessageCubit.dart';
import 'package:value_market_customer/commons/blocs/settingsAndLanguagesCubit.dart';
import 'package:value_market_customer/ui/profile/chat/models/chatMessage.dart';
import 'package:value_market_customer/ui/auth/repositories/authRepository.dart';
import 'package:value_market_customer/ui/profile/chat/repositories/chatRepository.dart';
import 'package:value_market_customer/ui/profile/customerSupport/widgets/chatController.dart';
import 'package:value_market_customer/ui/profile/customerSupport/widgets/messageItem.dart';
import 'package:value_market_customer/commons/widgets/customAppbar.dart';
import 'package:value_market_customer/commons/widgets/customBottomButtonContainer.dart';
import 'package:value_market_customer/commons/widgets/customCircularProgressIndicator.dart';
import 'package:value_market_customer/commons/widgets/customDefaultContainer.dart';
import 'package:value_market_customer/commons/widgets/customTextButton.dart';
import 'package:value_market_customer/commons/widgets/pusherClient.dart';

import 'package:value_market_customer/core/api/apiEndPoints.dart';
import 'package:value_market_customer/utils/designConfig.dart';
import 'package:value_market_customer/core/localization/labelKeys.dart';
import 'package:value_market_customer/utils/utils.dart';
import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:get/get.dart' as getX;
import 'package:get/route_manager.dart';
import 'package:pusher_channels_flutter/pusher_channels_flutter.dart';

class ChatScreen extends StatefulWidget {
  final int id;
  final bool isTicketChatScreen;
  const ChatScreen(
      {Key? key, required this.id, required this.isTicketChatScreen})
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
          key: chatScreenKey,
          id: Get.arguments['id'],
          isTicketChatScreen: Get.arguments['isTicketChatScreen'] ?? false,
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
final GlobalKey<_ChatScreenState> chatScreenKey = GlobalKey<_ChatScreenState>();

class _ChatScreenState extends State<ChatScreen> {
  List<ChatMessage> _messages = [];
  int totalMessages = 0;
  List<PlatformFile>? _selectedfiles;
  MultipartFile? file;
  final TextEditingController _controller = TextEditingController();
  final ChatController chatController = getX.Get.put(ChatController());

  @override
  void initState() {
    super.initState();
    // chatController.openChat(widget.id.toString());
    currentChatUserId = widget.id.toString();
    if (!widget.isTicketChatScreen) initPusher();

    listenToMessages();
  }

  initPusher() async {
    Future.delayed(Duration.zero, () {
      pusherService.initPusher(context, context.read<GetMessageCubit>());
    });
  }

  listenToMessages() {
    if (widget.isTicketChatScreen) {
      context.read<GetMessageCubit>().getTicketMessages(params: {
        ApiURL.ticketIdApiKey: widget.id.toString(),
      });
    } else {
      context.read<GetMessageCubit>().getMessages(params: {
        ApiURL.idApiKey: widget.id.toString(),
      });
    }
  }

  loadMoreMessages() {
    if (widget.isTicketChatScreen) {
      context.read<GetMessageCubit>().loadMore(params: {
        ApiURL.ticketIdApiKey: widget.id.toString(),
      }, isTicketChat: true);
    } else {
      context.read<GetMessageCubit>().loadMore(params: {
        ApiURL.idApiKey: widget.id.toString(),
      }, isTicketChat: false);
    }
  }

  @override
  void dispose() {
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return PopScope(
      onPopInvokedWithResult: (didPop, result) async {
        FocusScope.of(context).unfocus();
      },
      child: BlocConsumer<SendMessageCubit, SendMessageState>(
          listener: (context, state) {
        if (state is SendMessageSuccess) {
          _controller.clear();
          _selectedfiles = null;
          setState(() {
            _messages.add(state.chatMessage);
          });
        }
        if (state is SendMessageFailure) {
          Utils.showSnackBar(context: context, message: state.errorMessage);
        }
      }, builder: (context, sendMessagestate) {
        return BlocConsumer<GetMessageCubit, GetMessageState>(
            listener: (context, state) {
          if (state is GetMessageSuccess) {
            if (!widget.isTicketChatScreen)
              ChatRepository().makeSeenMessage(params: {
                ApiURL.idApiKey: widget.id.toString(),
              });
            setState(() {
              _messages = state.messages.reversed.toList();
              totalMessages = state.total;
            });
          }
        }, builder: (context, state) {
          return Scaffold(
              appBar: const CustomAppbar(titleKey: chatKey),
              bottomNavigationBar: _buildMessageInput(sendMessagestate),
              body: state is GetMessageInProgress
                  ? CustomCircularProgressIndicator(
                      indicatorColor: Theme.of(context).colorScheme.primary)
                  : NotificationListener<ScrollUpdateNotification>(
                      onNotification: (notification) {
                        if (notification.metrics.pixels >=
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
                          final bool isAdmin = message.fromId !=
                              AuthRepository.getUserDetails().id;
                          // Check if it's the first message from Customer Care or of the same message as previous
                          bool showLabel = isAdmin &&
                              (reversedIndex == 0 ||
                                  !(_messages[reversedIndex - 1].fromId !=
                                      AuthRepository.getUserDetails().id));
                          return MessageItem(
                              message: message,
                              showLabel: widget.isTicketChatScreen
                                  ? showLabel
                                  : false);
                        },
                      ),
                    ));
        });
      }),
    );
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
          if (_selectedfiles != null) attachmentItem(),
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
    return Column(
        spacing: 8,
        children: List.generate(_selectedfiles!.length, (index) {
          return CustomDefaultContainer(
              child: Row(
            children: <Widget>[
              const Icon(
                Icons.file_copy_outlined,
                size: 30,
              ),
              Expanded(child: Text(_selectedfiles![index].name)),
              const Spacer(),
              IconButton(
                icon: Icon(Icons.cancel_outlined,
                    size: 30, color: Theme.of(context).colorScheme.primary),
                onPressed: () {
                  setState(() {
                    _selectedfiles!.removeAt(index);
                  });
                },
              )
            ],
          ));
        }));
  }

  void pickFile() async {
    FilePickerResult? result = await FilePicker.platform.pickFiles(
        type: FileType.any, // Allow any type of file
        allowMultiple: widget.isTicketChatScreen);

    if (result != null) {
      _selectedfiles = result.files;
      setState(() {});
      // Send the file using your send_message method
    }
  }

  void _sendMessage() {
    if (_controller.text.trim().isEmpty && _selectedfiles == null) return;

    sendMessage(_controller.text);
  }

  void sendMessage(String text) async {
    if (widget.isTicketChatScreen) {
      Map<String, dynamic> params = {
        ApiURL.userTypeApiKey: 'user',
        ApiURL.ticketIdApiKey: widget.id.toString(),
        ApiURL.messageApiKey: text,
      };
      if (_selectedfiles != null) {
        for (int i = 0; i < _selectedfiles!.length; i++) {
          MultipartFile file = await MultipartFile.fromFile(
            _selectedfiles![i].path!,
          );
          params['attachments[$i]'] = file;
        }
      }
      context.read<SendMessageCubit>().sendTicketMessage(params: params);
    } else {
      if (_selectedfiles != null) {
        file = await MultipartFile.fromFile(
          _selectedfiles!.first.path!,
        );
      }
      context.read<SendMessageCubit>().sendMessage(params: {
        ApiURL.fromIdApiKey: AuthRepository.getUserDetails().id,
        ApiURL.idApiKey: widget.id.toString(),
        ApiURL.messageApiKey: text,
        if (file != null) ApiURL.fileApiKey: file,
      });
    }
  }
}
