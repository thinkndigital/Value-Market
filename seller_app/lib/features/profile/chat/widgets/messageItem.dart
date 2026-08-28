import 'package:value_market_seller/features/auth/repositories/authRepository.dart';
import 'package:value_market_seller/features/profile/chat/blocs/downloadFileCubit.dart';
import 'package:value_market_seller/features/profile/chat/models/chatMessage.dart';
import 'package:value_market_seller/commons/widgets/customImageWidget.dart';
import 'package:value_market_seller/utils/designConfig.dart';
import 'package:value_market_seller/utils/utils.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:open_filex/open_filex.dart';

class MessageItem extends StatelessWidget {
  final ChatMessage message;
  final bool showLabel;
  MessageItem({Key? key, required this.message, required this.showLabel})
      : super(key: key);
  String? fileUrl = "", filetype = "";
  @override
  Widget build(BuildContext context) {
    bool isSentByMe = message.fromId == AuthRepository.getUserId();
    if (message.attachment != null &&
        (message.attachment!['new_name'] != null ||
            message.attachment!['file'] != null)) {
      fileUrl = message.attachment!['new_name'] ?? message.attachment!['file'];

      if (fileUrl != null) {
        filetype = fileUrl!.split('.').last;
      }
    }
    // Check if it's the first message from Customer Care

    return BlocProvider(
      create: (context) => DownloadFileCubit(),
      child: Column(
        crossAxisAlignment:
            isSentByMe ? CrossAxisAlignment.end : CrossAxisAlignment.start,
        children: [
          // if (showLabel)
          //   Padding(
          //       padding: const EdgeInsets.only(bottom: 4.0, top: 12),
          //       child: CustomTextContainer(
          //         textKey: customerSupportKey,
          //         style: Theme.of(context).textTheme.labelMedium,
          //       )),
          LayoutBuilder(builder: (context, constraints) {
            double maxWidth = constraints.maxWidth * 0.5;
            return BlocBuilder<DownloadFileCubit, DownloadFileState>(
              builder: (context, state) {
                return Stack(
                  alignment: Alignment.center,
                  children: [
                    Container(
                        constraints: BoxConstraints(
                          maxWidth:
                              maxWidth, // Limit max width to half the screen
                        ),
                        padding: const EdgeInsets.all(8),
                        margin: const EdgeInsets.only(bottom: 8),
                        foregroundDecoration: state is DownloadFileInProgress
                            ? BoxDecoration(
                                color: Colors.black.withValues(alpha: 0.5),
                                borderRadius: BorderRadius.circular(12.0),
                              )
                            : null,
                        decoration: BoxDecoration(
                          color: isSentByMe
                              ? Theme.of(context).colorScheme.primary
                              : Theme.of(context)
                                  .colorScheme
                                  .secondary
                                  .withValues(alpha: 0.67),
                          borderRadius: BorderRadius.circular(5),
                        ),
                        child: message.attachment != null &&
                                (message.attachment!['new_name'] != null ||
                                    message.attachment!['file'] != null)
                            ? attachmentWidget(context)
                            : _buildTextMessage(context)),
                    if (state is DownloadFileInProgress)
                      CircularProgressIndicator(
                        strokeWidth: 1.5,
                        value: state.uploadedPercentage / 100,
                        backgroundColor: Colors.white,
                        valueColor: AlwaysStoppedAnimation<Color>(Colors.blue),
                      ),
                  ],
                );
              },
            );
          }),
        ],
      ),
    );
  }

  Widget _buildTextMessage(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      mainAxisSize: MainAxisSize.min,
      children: [
        if (message.body.isNotEmpty)
          Text(message.body,
              overflow: TextOverflow.visible,
              style: Theme.of(context)
                  .textTheme
                  .bodyMedium!
                  .copyWith(color: Theme.of(context).colorScheme.onPrimary)),
        const SizedBox(
          height: 2,
        ),
        Text(message.createdAt ?? '',
            style: Theme.of(context).textTheme.bodySmall!.copyWith(
                color: Theme.of(context)
                    .colorScheme
                    .onPrimary
                    .withValues(alpha: 0.6))),
      ],
    );
  }

  attachmentWidget(BuildContext context) {
    return BlocConsumer<DownloadFileCubit, DownloadFileState>(
      listener: (context, state) async {
        if (state is DownloadFileSuccess) {
          await OpenFilex.open(state.downloadedFilePath);
        }
        if (state is DownloadFileFailure) {
          Utils.showSnackBar(message: state.errorMessage);
        }
      },
      builder: (context, state) {
        return Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            GestureDetector(
              onTap: () async {
                String downloadFilePath = await Utils.getDowanloadFilePath(
                    fileUrl!.substring(fileUrl!.lastIndexOf('/') + 1));

                bool exists = await Utils.fileExists(downloadFilePath);
                if (exists) {
                  OpenFilex.open(downloadFilePath);
                } else {
                  context
                      .read<DownloadFileCubit>()
                      .downloadFile(fileUrl: fileUrl!);
                }
              },
              child: Utils.isImageUrl(fileUrl!)
                  ? CustomImageWidget(
                      url: fileUrl!,
                      height: 150,
                      width: 150,
                      borderRadius: 5,
                    )
                  : Row(
                      mainAxisSize: MainAxisSize.min,
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: <Widget>[
                        Icon(
                          Icons.file_copy_outlined,
                          color: Theme.of(context).colorScheme.onPrimary,
                          size: 25,
                        ),
                        Expanded(
                          child: Text(
                            message.attachment!['old_name'] ??
                                message.attachment!['title'] ??
                                '',
                            style: TextStyle(
                                color: Theme.of(context).colorScheme.onPrimary),
                          ),
                        ),
                      ],
                    ),
            ),
            DesignConfig.smallHeightSizedBox,
            _buildTextMessage(context)
          ],
        );
      },
    );
  }
}
