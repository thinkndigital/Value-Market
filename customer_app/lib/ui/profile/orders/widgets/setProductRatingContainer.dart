import 'dart:io';

import 'package:dio/dio.dart';
import 'package:eshop_plus/ui/profile/orders/blocs/setProductReviewCubit.dart';
import 'package:eshop_plus/commons/blocs/settingsAndLanguagesCubit.dart';
import 'package:eshop_plus/ui/profile/orders/models/order.dart';
import 'package:eshop_plus/commons/widgets/customCircularProgressIndicator.dart';
import 'package:eshop_plus/commons/widgets/customRoundedButton.dart';
import 'package:eshop_plus/commons/widgets/customTextContainer.dart';
import 'package:eshop_plus/commons/widgets/dottedLineRectPainter.dart';

import 'package:eshop_plus/core/api/apiEndPoints.dart';
import 'package:eshop_plus/core/constants/appConstants.dart';
import 'package:eshop_plus/core/constants/themeConstants.dart';
import 'package:eshop_plus/utils/designConfig.dart';
import 'package:eshop_plus/core/localization/labelKeys.dart';
import 'package:eshop_plus/utils/utils.dart';
import 'package:eshop_plus/utils/validator.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:image_picker/image_picker.dart';

class SetProductRatingContainer extends StatefulWidget {
  final OrderItems orderItem;
  const SetProductRatingContainer({Key? key, required this.orderItem})
      : super(key: key);

  @override
  _SetProductRatingContainerState createState() =>
      _SetProductRatingContainerState();
}

class _SetProductRatingContainerState extends State<SetProductRatingContainer> {
  int _rating = 0; // Initial rating value
  final TextEditingController _titleController = TextEditingController();
  final TextEditingController _reviewController = TextEditingController();
  bool _showForm = false;
  final GlobalKey<FormState> _formKey = GlobalKey<FormState>();
  final ImagePicker _picker = ImagePicker();
  List<File> _selectedImages = [];
  List<dynamic> _images = [];
  @override
  void initState() {
    super.initState();

    if (widget.orderItem.userRating != "0" &&
        widget.orderItem.userRating != "") {
      double doubleValue = double.parse(widget.orderItem.userRating!);

      // Convert double to integer
      _rating = doubleValue.toInt();

      _reviewController.text = widget.orderItem.userRatingComment ?? '';
      _titleController.text = widget.orderItem.userRatingTitle ?? '';
    }
  }

  @override
  Widget build(BuildContext context) {
    return BlocListener<SetProductReviewCubit, SetProductReviewState>(
      listener: (context, state) {
        if (state is SetProductReviewSuccess) {
          _showForm = false;

          widget.orderItem.userRating = state.productRating.rating.toString();
          widget.orderItem.userRatingComment = state.productRating.comment;
          widget.orderItem.userRatingTitle = state.productRating.title;
          widget.orderItem.userRatingImages = state.productRating.images!
              .map((image) => image.toString())
              .toList();

          setState(() {});
        }
        if (state is SetProductReviewFailure) {
          Utils.showSnackBar(message: state.errorMessage, context: context);
        }
      },
      child: Container(
        color: Theme.of(context).colorScheme.primaryContainer,
        child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              DesignConfig.defaultHeightSizedBox,
              Padding(
                padding: const EdgeInsetsDirectional.symmetric(
                    horizontal: appContentHorizontalPadding),
                child: CustomTextContainer(
                  textKey: rateTheProductKey,
                  style: Theme.of(context).textTheme.titleMedium,
                ),
              ),
              const Divider(
                height: 12,
                thickness: 0.5,
              ),
              Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: List.generate(5, (index) {
                  return IconButton(
                    icon: Icon(
                      Icons.star_rounded,
                      color: index < _rating
                          ? Theme.of(context).colorScheme.primary
                          : Theme.of(context)
                              .colorScheme
                              .secondary
                              .withValues(alpha: 0.10),
                      size: 42,
                    ),
                    onPressed: () {
                      setState(() {
                        if (_rating == index + 2) {
                          _rating = index + 1;
                        } else {
                          _rating = index + 1;
                        }
                        _showForm = true;
                      });
                    },
                  );
                }),
              ),
              const Divider(
                height: 12,
                thickness: 0.5,
              ),
              if (_showForm) ...[
                Form(
                  key: _formKey,
                  child: Padding(
                      padding: const EdgeInsetsDirectional.symmetric(
                          horizontal: appContentHorizontalPadding),
                      child: Column(
                        children: <Widget>[
                          buildImageSection(),
                          buildTextField(
                              controller: _titleController,
                              hintText: writeTitleKey,
                              validator: (v) =>
                                  Validator.emptyValueValidation(context, v),
                              labelKey: addTitleKey),
                          DesignConfig.defaultHeightSizedBox,
                          buildTextField(
                              controller: _reviewController,
                              hintText: writeReviewKey,
                              labelKey: addReviewKey,
                              maxLines: 3),
                          DesignConfig.defaultHeightSizedBox,
                          BlocConsumer<SetProductReviewCubit,
                              SetProductReviewState>(
                            listener: (context, state) {},
                            builder: (context, state) {
                              return CustomRoundedButton(
                                widthPercentage: 1.0,
                                buttonTitle:
                                    widget.orderItem.userRating != "0" &&
                                            widget.orderItem.userRating != ""
                                        ? updateReviewKey
                                        : submitReviewKey,
                                showBorder: false,
                                child: state is SetProductReviewInProgress
                                    ? const CustomCircularProgressIndicator()
                                    : null,
                                onTap: () async {
                                  if (state is SetProductReviewInProgress) {
                                    return;
                                  }
                                  FocusScope.of(context).unfocus();

                                  if (_formKey.currentState!.validate()) {
                                    Map<String, dynamic> params = {
                                      ApiURL.productIdApiKey:
                                          widget.orderItem.productId,
                                      ApiURL.ratingApiKey: _rating,
                                      ApiURL.commentApiKey:
                                          _reviewController.text.trim(),
                                      ApiURL.titleApiKey:
                                          _titleController.text.trim(),
                                    };
                                    if (_images.isNotEmpty) {
                                      for (int i = 0; i < _images.length; i++) {
                                        if (_images[i] is String) {
                                          params['review_image[$i]'] =
                                              _images[i];
                                          continue;
                                        } else {
                                          MultipartFile file =
                                              await MultipartFile.fromFile(
                                            _images[i].path,
                                          );
                                          params['review_image[$i]'] = file;
                                        }
                                      }
                                    }

                                    context
                                        .read<SetProductReviewCubit>()
                                        .setProductReview(
                                            params: params,
                                            apiUrl: widget
                                                        .orderItem.orderType ==
                                                    comboOrderType
                                                ? ApiURL.setComboProductRating
                                                : ApiURL.setProductRating);
                                  }
                                },
                              );
                            },
                          ),
                          DesignConfig.defaultHeightSizedBox,
                        ],
                      )),
                )
              ]
            ]),
      ),
    );
  }

  Widget buildTextField(
      {required TextEditingController controller,
      required String hintText,
      required String labelKey,
      FocusNode? focusNode,
      final Function? validator,
      int? maxLines}) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        CustomTextContainer(
          textKey: labelKey,
          style: Theme.of(context).textTheme.titleSmall,
        ),
        DesignConfig.smallHeightSizedBox,
        TextFormField(
          controller: controller,
          focusNode: focusNode,
          textAlign: TextAlign.left,
          validator: validator != null ? (val) => validator(val) : null,
          decoration: InputDecoration(
            fillColor: Theme.of(context).scaffoldBackgroundColor,
            filled: true,
            enabledBorder: OutlineInputBorder(
                borderSide: const BorderSide(color: Colors.transparent),
                borderRadius: BorderRadius.circular(8)),
            focusedBorder: OutlineInputBorder(
                borderSide: const BorderSide(color: Colors.transparent),
                borderRadius: BorderRadius.circular(8)),
            contentPadding: const EdgeInsets.all(15),
            hintText: context
                .read<SettingsAndLanguagesCubit>()
                .getTranslatedValue(labelKey: labelKey),
            hintStyle: Theme.of(context).textTheme.bodyMedium!.copyWith(
                  color: Theme.of(context)
                      .colorScheme
                      .secondary
                      .withValues(alpha: 0.67),
                ),
          ),
          maxLines: maxLines ?? 1,
          cursorHeight: 22,
        ),
      ],
    );
  }

  // Function to pick images
  Future<void> _pickImage() async {
    final List<XFile>? pickedFiles =
        await _picker.pickMultiImage(); // Allows multiple image selection
    if (pickedFiles != null) {
      setState(() {
        // Add picked images to the list
        _selectedImages
            .addAll(pickedFiles.map((file) => File(file.path)).toList());
        _images.addAll(_selectedImages);
      });
    }
  }

  // Function to remove an image from the list
  void _removeImage(int index) {
    setState(() {
      _images.removeAt(index); // Remove the image from the list
    });
  }

  buildImageSection() {
    return Padding(
      padding:
          const EdgeInsets.symmetric(vertical: appContentHorizontalPadding),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          CustomTextContainer(
            textKey: shareImageKey,
            style: Theme.of(context).textTheme.titleSmall,
          ),
          DesignConfig.smallHeightSizedBox,
          SizedBox(
            height: 114,
            child: ListView(
              scrollDirection: Axis.horizontal,
              children: [
                // Image picker icon
                GestureDetector(
                  onTap: _pickImage,
                  child: CustomPaint(
                    painter: DottedLineRectPainter(
                      strokeWidth: 1.0,
                      radius: 5.33,
                      dashWidth: 4.0,
                      dashSpace: 3.0,
                      color: Theme.of(context).inputDecorationTheme.iconColor!,
                    ),
                    child: SizedBox(
                      width: 114,
                      height: 114,
                      child: Icon(
                        Icons.camera_alt_outlined,
                        color: Theme.of(context)
                            .colorScheme
                            .secondary
                            .withValues(alpha: 0.67),
                        size: 30,
                      ),
                    ),
                  ),
                ),

                // Display selected images in a horizontal scrollable view
                ListView.builder(
                  scrollDirection: Axis.horizontal,
                  itemCount: _images.length,
                  shrinkWrap: true,
                  physics: const NeverScrollableScrollPhysics(),
                  itemBuilder: (context, index) {
                    return Stack(
                      children: [
                        Container(
                          margin: const EdgeInsets.only(left: 8),
                          width: 114,
                          height: 114,
                          decoration: BoxDecoration(
                            borderRadius: BorderRadius.circular(8),
                            image: _images[index] is String
                                ? DecorationImage(
                                    image: NetworkImage(_images[index]),
                                    fit: BoxFit.cover,
                                  )
                                : DecorationImage(
                                    image: FileImage(_images[index]),
                                    fit: BoxFit.cover,
                                  ),
                          ),
                        ),
                        // Remove button
                        Positioned(
                          right: 5,
                          top: 5,
                          child: GestureDetector(
                              onTap: () => _removeImage(index),
                              child: Container(
                                width: 20,
                                height: 20,
                                alignment: Alignment.center,
                                padding: const EdgeInsets.all(2),
                                decoration: BoxDecoration(
                                  color:
                                      Theme.of(context).colorScheme.onPrimary,
                                  shape: BoxShape.circle,
                                ),
                                child: Icon(Icons.close,
                                    size: 16,
                                    color:
                                        Theme.of(context).colorScheme.primary),
                              )),
                        ),
                      ],
                    );
                  },
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
