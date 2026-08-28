import 'package:dio/dio.dart';
import 'package:eshop_plus/commons/widgets/customCircularProgressIndicator.dart';
import 'package:eshop_plus/core/constants/themeConstants.dart';
import 'package:eshop_plus/core/localization/labelKeys.dart';
import 'package:eshop_plus/core/theme/colors.dart';
import 'package:eshop_plus/ui/profile/orders/blocs/sendBankTransferProofCubit.dart';
import 'package:eshop_plus/utils/designConfig.dart';
import 'package:eshop_plus/utils/utils.dart';
import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import '../../../../../commons/widgets/customRoundedButton.dart';
import '../../../../../commons/widgets/customTextContainer.dart';
import '../models/order.dart';

class BankReceiptUploadSection extends StatefulWidget {
  final Order order;
  const BankReceiptUploadSection({Key? key, required this.order})
      : super(key: key);

  @override
  State<BankReceiptUploadSection> createState() =>
      _BankReceiptUploadSectionState();
}

class _BankReceiptUploadSectionState extends State<BankReceiptUploadSection> {
  List<PlatformFile> _selectedFiles = [];

  void _pickFiles() async {
    FilePickerResult? result =
        await FilePicker.platform.pickFiles(allowMultiple: true);
    if (result != null) {
      setState(() {
        _selectedFiles.addAll(result.files);
      });
    }
  }

  void _resetFiles() {
    setState(() {
      _selectedFiles = [];
    });
  }

  void _sendFiles() async {
    if (_selectedFiles.isEmpty) {
      Utils.showSnackBar(message: selectFileWarningKey);
      return;
    }

    List<MultipartFile> attachments = [];
    for (var file in _selectedFiles) {
      if (file.path != null) {
        attachments.add(await MultipartFile.fromFile(file.path!));
      }
    }
    context.read<SendBankTransferProofCubit>().sendBankTransferProof(
        orderId: widget.order.id!, attachments: attachments);
  }

  Widget _buildAttachmentStatus(int status, BuildContext context) {
    String label;
    Color color;
    switch (status) {
      case 1:
        label = rejectedKey;
        color = cancelledStatusColor;
        break;
      case 2:
        label = acceptedKey;
        color = successStatusColor;
        break;
      default:
        label = pendingKey;
        color = pendingStatusColor;
    }
    return Container(
      margin: const EdgeInsets.only(left: 8),
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.1),
        borderRadius: BorderRadius.circular(8),
      ),
      child: CustomTextContainer(
        textKey: label,
        style: Theme.of(context)
            .textTheme
            .bodySmall
            ?.copyWith(color: color, fontWeight: FontWeight.bold),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return BlocConsumer<SendBankTransferProofCubit, SendBankTransferProofState>(
      listener: (context, state) {
        if (state is SendBankTransferProofSuccess) {
          setState(() {
            _selectedFiles = [];
            // Add new attachments to the order locally
            if (state.attachmentUrls.isNotEmpty) {
              widget.order.attachments ??= [];
              for (final url in state.attachmentUrls) {
                // Use a negative timestamp as a temporary ID
                widget.order.attachments!.add(OrderAttachment(
                  id: DateTime.now().millisecondsSinceEpoch * -1,
                  attachment: url,
                  banktransferStatus: 0, // Pending
                ));
              }
            }
          });
          Utils.showSnackBar(message: state.message);
        } else if (state is SendBankTransferProofFailure) {
          Utils.showSnackBar(message: state.errorMessage);
        }
      },
      builder: (context, state) {
        return Column(
          children: [
            Container(
              padding: const EdgeInsetsDirectional.symmetric(
                  vertical: appContentVerticalSpace),
              color: Theme.of(context).colorScheme.primaryContainer,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Padding(
                    padding: const EdgeInsetsDirectional.symmetric(
                        horizontal: appContentHorizontalPadding),
                    child: CustomTextContainer(
                      textKey: bankPaymentReceiptKey,
                      style: Theme.of(context).textTheme.titleMedium,
                    ),
                  ),
                  const Divider(
                    height: 12,
                    thickness: 0.5,
                  ),
                  Padding(
                    padding: const EdgeInsetsDirectional.symmetric(
                        horizontal: appContentHorizontalPadding),
                    child: Column(
                      children: <Widget>[
                        DesignConfig.smallHeightSizedBox,
                        CustomRoundedButton(
                          buttonTitle: '',
                          showBorder: false,
                          backgroundColor: Theme.of(context)
                              .colorScheme
                              .primary
                              .withValues(alpha: 0.1),
                          child: Row(
                            mainAxisAlignment: MainAxisAlignment.center,
                            children: [
                              Icon(Icons.attach_file,
                                  color: Theme.of(context).colorScheme.primary),
                              DesignConfig.smallWidthSizedBox,
                              CustomTextContainer(
                                textKey: chooseFilesKey,
                                style: Theme.of(context).textTheme.bodyMedium,
                                overflow: TextOverflow.ellipsis,
                              ),
                            ],
                          ),
                          onTap: _pickFiles,
                        ),
                        DesignConfig.smallHeightSizedBox,
                        // Show order attachments with status if present
                        if (widget.order.attachments != null &&
                            widget.order.attachments!.isNotEmpty)
                          Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              CustomTextContainer(
                                textKey: uploadedFilesKey,
                                style: Theme.of(context)
                                    .textTheme
                                    .titleSmall
                                    ?.copyWith(fontWeight: FontWeight.bold),
                              ),
                              const SizedBox(height: 8),
                              ...widget.order.attachments!.map((attachment) =>
                                  Padding(
                                    padding: const EdgeInsets.only(bottom: 4.0),
                                    child: Row(
                                      mainAxisAlignment:
                                          MainAxisAlignment.spaceBetween,
                                      children: [
                                        Expanded(
                                          flex: 2,
                                          child: GestureDetector(
                                            onTap: () => Utils.launchURL(
                                                attachment.attachment),
                                            child: Row(
                                              mainAxisSize: MainAxisSize.min,
                                              children: [
                                                Icon(Icons.link,
                                                    size: 16,
                                                    color: Theme.of(context)
                                                        .colorScheme
                                                        .primary),
                                                const SizedBox(width: 8),
                                                Expanded(
                                                  child: Text(
                                                    attachment.attachment
                                                        .split('/')
                                                        .last,
                                                    style: Theme.of(context)
                                                        .textTheme
                                                        .bodySmall
                                                        ?.copyWith(
                                                          color:
                                                              Theme.of(context)
                                                                  .colorScheme
                                                                  .primary,
                                                          decoration:
                                                              TextDecoration
                                                                  .underline,
                                                        ),
                                                    maxLines: 1,
                                                    overflow:
                                                        TextOverflow.ellipsis,
                                                  ),
                                                ),
                                              ],
                                            ),
                                          ),
                                        ),
                                        Flexible(
                                          child: _buildAttachmentStatus(
                                              attachment.banktransferStatus,
                                              context),
                                        ),
                                      ],
                                    ),
                                  )),
                            ],
                          ),
                        DesignConfig.smallHeightSizedBox,

                        if (_selectedFiles.isNotEmpty)
                          Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: _selectedFiles
                                .asMap()
                                .entries
                                .map((entry) => Row(
                                      mainAxisAlignment:
                                          MainAxisAlignment.spaceBetween,
                                      children: [
                                        Expanded(
                                            child: Text(entry.value.name,
                                                overflow:
                                                    TextOverflow.ellipsis)),
                                        IconButton(
                                          icon:
                                              const Icon(Icons.close, size: 18),
                                          onPressed: () {
                                            setState(() {
                                              _selectedFiles
                                                  .removeAt(entry.key);
                                            });
                                          },
                                        ),
                                      ],
                                    ))
                                .toList(),
                          ),
                        // Show uploaded attachments if available
                        // if (state is SendBankTransferProofSuccess &&
                        //     state.attachmentUrls.isNotEmpty)
                        //   Column(
                        //     crossAxisAlignment: CrossAxisAlignment.start,
                        //     children: [
                        //       ...state.attachmentUrls
                        //           .map((url) => Padding(
                        //                 padding:
                        //                     const EdgeInsets.only(bottom: 8.0),
                        //                 child: GestureDetector(
                        //                   onTap: () => Utils.launchURL(url),
                        //                   child: Row(
                        //                     children: [
                        //                       Icon(
                        //                         Icons.link,
                        //                         size: 16,
                        //                         color: Theme.of(context)
                        //                             .colorScheme
                        //                             .primary,
                        //                       ),
                        //                       const SizedBox(width: 8),
                        //                       Expanded(
                        //                         child: Text(
                        //                           url.split('/').last,
                        //                           style: Theme.of(context)
                        //                               .textTheme
                        //                               .bodySmall
                        //                               ?.copyWith(
                        //                                 color: Theme.of(context)
                        //                                     .colorScheme
                        //                                     .primary,
                        //                                 decoration:
                        //                                     TextDecoration
                        //                                         .underline,
                        //                               ),
                        //                           overflow:
                        //                               TextOverflow.ellipsis,
                        //                         ),
                        //                       ),
                        //                     ],
                        //                   ),
                        //                 ),
                        //               ))
                        //           .toList(),
                        //     ],
                        //   ),

                        if (_selectedFiles.isNotEmpty) ...[
                          DesignConfig.defaultHeightSizedBox,
                          Row(
                            children: [
                              Expanded(
                                child: CustomRoundedButton(
                                  buttonTitle: resetKey,
                                  showBorder: true,
                                  borderColor: Theme.of(context)
                                      .inputDecorationTheme
                                      .iconColor,
                                  backgroundColor: Theme.of(context)
                                      .colorScheme
                                      .primaryContainer,
                                  onTap:
                                      state is SendBankTransferProofInProgress
                                          ? null
                                          : _resetFiles,
                                  style: Theme.of(context)
                                      .textTheme
                                      .titleMedium!
                                      .copyWith(
                                          color: Theme.of(context)
                                              .colorScheme
                                              .secondary),
                                  height: 40,
                                ),
                              ),
                              const SizedBox(width: 12),
                              Expanded(
                                child: CustomRoundedButton(
                                  buttonTitle: sendKey,
                                  showBorder: false,
                                  onTap: _sendFiles,
                                  height: 40,
                                  child:
                                      state is SendBankTransferProofInProgress
                                          ? CustomCircularProgressIndicator(
                                              indicatorColor: Theme.of(context)
                                                  .colorScheme
                                                  .primaryContainer,
                                            )
                                          : null,
                                ),
                              ),
                            ],
                          ),
                        ]
                      ],
                    ),
                  )
                ],
              ),
            ),
            DesignConfig.smallHeightSizedBox,
          ],
        );
      },
    );
  }
}
