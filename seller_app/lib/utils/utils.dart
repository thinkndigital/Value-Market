import 'dart:io';

import 'package:device_info_plus/device_info_plus.dart';
import 'package:value_market_seller/commons/blocs/settingsAndLanguagesCubit.dart';
import 'package:value_market_seller/commons/models/language.dart';
import 'package:value_market_seller/commons/widgets/customLabelContainer.dart';
import 'package:value_market_seller/commons/widgets/customModalBottomSheet.dart';
import 'package:value_market_seller/commons/widgets/customRoundedButton.dart';
import 'package:value_market_seller/commons/widgets/dottedLineRectPainter.dart';
import 'package:value_market_seller/core/api/apiService.dart';
import 'package:value_market_seller/core/theme/colors.dart';
import 'package:value_market_seller/features/auth/repositories/authRepository.dart';
import 'package:value_market_seller/main.dart';
import 'package:value_market_seller/features/orders/models/order.dart';
import 'package:value_market_seller/core/constants/appConstants.dart';
import 'package:value_market_seller/core/constants/themeConstants.dart';
import 'package:value_market_seller/core/localization/labelKeys.dart';
import 'package:value_market_seller/utils/designConfig.dart';
import 'package:external_path/external_path.dart';
import 'package:value_market_seller/commons/models/systemSettings.dart';

import 'package:value_market_seller/commons/widgets/customTextContainer.dart';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_svg/svg.dart';
import 'package:flutter_widget_from_html/flutter_widget_from_html.dart';
import 'package:get/get.dart';
import 'package:intl/intl.dart';
import 'package:path_provider/path_provider.dart';
import 'package:permission_handler/permission_handler.dart';
import 'package:url_launcher/url_launcher.dart';

class Utils {
  static Locale getLocaleFromLanguageCode(String languageCode) {
    List<String> result = languageCode.split("-");
    return result.length == 1
        ? Locale(result.first)
        : Locale(result.first, result.last);
  }

  static String getImagePath(String imageName) {
    return "assets/images/$imageName";
  }

  static String getBrandingImagePath(String imageName) {
    return "assets/images/branding/$imageName";
  }

  static setSvgImage(String imageName,
      {Color? color, double? width, double? height}) {
    return SvgPicture.asset("assets/images/$imageName.svg",
        colorFilter:
            color != null ? ColorFilter.mode(color, BlendMode.srcIn) : null,
        width: width,
        height: height);
  }

  static String getLottieAnimationPath(String animationFileName) {
    return "assets/animations/$animationFileName";
  }

  static Future<dynamic> showBottomSheet(
      {required Widget child,
      required BuildContext context,
      bool? enableDrag}) async {
    final result = Get.bottomSheet(child,
        enableDrag: enableDrag ?? false,
        isScrollControlled: true,
        backgroundColor: Theme.of(context).scaffoldBackgroundColor,
        shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.only(
                topLeft: Radius.circular(bottomsheetBorderRadius),
                topRight: Radius.circular(bottomsheetBorderRadius))));
    return result;
  }

  static Future<void> showSnackBar(
      {required String message,
      BuildContext? context,
      TextStyle? messageTextStyle,
      SnackBarAction? action,
      Duration? msgDuration,
      Color? backgroundColor}) async {
    if (Get.isSnackbarOpen) {
      Get.back(); // closes the current snackbar immediately
    }

    Get.showSnackbar(GetSnackBar(
        snackbarStatus: (status) {
          if (status == SnackbarStatus.CLOSED) {}
        },
        messageText: CustomTextContainer(
          textKey: message,
          style: messageTextStyle ??
              TextStyle(
                fontWeight: FontWeight.w500,
                fontSize: 15.5,
                color: Theme.of(Get.context!).colorScheme.onSecondary,
              ),
        ),
        isDismissible: true,
        snackStyle: SnackStyle.FLOATING,
        mainButton: action ??
            SnackBarAction(label: 'Close', onPressed: () => Get.back()),
        duration: msgDuration ?? snackBarDuration,
        dismissDirection: DismissDirection.none,
        snackPosition: SnackPosition.BOTTOM,
        margin: const EdgeInsets.all(10),
        borderRadius: 4,
        backgroundColor: backgroundColor ??
            Theme.of(navigatorKey.currentContext!)
                .colorScheme
                .secondary
                .withValues(alpha: 0.95),
        padding: const EdgeInsets.symmetric(vertical: 12.5, horizontal: 15)));
  }

  static String fromTime(
      {required TimeOfDay timeOfDay, required BuildContext context}) {
    return timeOfDay.format(context);
  }

  static bool isUserLoggedIn() {
    return AuthRepository.getIsLogIn();
  }

  static int getHourFromTimeDetails({required String time}) {
    final timeDetails = time.split(":");
    return int.parse(timeDetails[0]);
  }

  static int getMinuteFromTimeDetails({required String time}) {
    final timeDetails = time.split(":");
    return int.parse(timeDetails[1]);
  }

  static Color? getColorFromHexValue(String hexValue) {
    if (hexValue.isEmpty) {
      return null;
    }
    final int color = int.parse(hexValue.replaceAll("#", "0xff"));
    return Color(color);
  }

  static Future<dynamic>? navigateToScreen(BuildContext context, String route,
      {dynamic arguments,
      int? id,
      bool preventDuplicates = true,
      Map<String, String>? parameters,
      bool? replaceAll = false,
      bool? replacePrevious = false}) async {
    if (replacePrevious == true) {
      return await Get.offNamed(route,
          arguments: arguments, preventDuplicates: true);
    } else if (replaceAll == true) {
      Get.offAllNamed(route);
    } else {
      return await Get.toNamed(route,
          arguments: arguments, preventDuplicates: true);
    }
  }

  static popNavigation(BuildContext context) {
    Get.back();
  }

  static buildSignupHeader(BuildContext context, String key) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 8),
      child: CustomTextContainer(
        textKey: key,
        style: Theme.of(context).textTheme.titleMedium,
        textAlign: TextAlign.start,
      ),
    );
  }

  static Future<List<File?>?> openFileExplorer(
      {FileType? fileType, bool isMultiple = false}) async {
    try {
      // Pick a single file
      FilePickerResult? result = await FilePicker.platform
          .pickFiles(type: fileType ?? FileType.any, allowMultiple: isMultiple);
      List<File> files = [];
      if (result != null) {
        if (isMultiple) {
          files.addAll(result.paths.map((path) => File(path!)).toList());
        } else {
          files.add(File(result.files.single.path!));
        }
        return files;
      }
      return null;
      // Use the file path
    } catch (e) {}
    return null;
  }

  static buildImageUploadWidget(
      {required BuildContext context,
      required String labelKey,
      required VoidCallback onTapUpload,
      required File? file,
      bool? isFieldValueMandatory = true,
      String? imgurl,
      required VoidCallback onTapClose}) {
    Size size = MediaQuery.of(context).size;
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 7.5),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          CustomLabelContainer(
            textKey: labelKey,
            isFieldValueMandatory: isFieldValueMandatory,
          ),
          const SizedBox(
            height: 10,
          ),
          GestureDetector(
            onTap: onTapUpload,
            child: CustomPaint(
              painter: DottedLineRectPainter(
                strokeWidth: 1.0,
                radius: 8,
                dashWidth: 4.0,
                dashSpace: 2.0,
                color: Theme.of(context)
                    .colorScheme
                    .secondary
                    .withValues(alpha: 0.8),
              ),
              child: Container(
                  width: size.width * 0.4,
                  height: size.width * 0.4,
                  alignment: Alignment.center,
                  decoration: BoxDecoration(
                    color: Colors.blue.withValues(alpha: 0.1),
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: file != null ||
                          (imgurl != null && imgurl.trim().isNotEmpty)
                      ? Stack(
                          clipBehavior: Clip.none,
                          children: [
                            Container(
                              width: size.width * 0.4,
                              height: size.width * 0.4,
                              decoration: BoxDecoration(
                                  borderRadius: BorderRadius.circular(8),
                                  image: DecorationImage(
                                    image: file != null
                                        ? Image.file(file).image
                                        : NetworkImage(imgurl!),
                                    fit: BoxFit.cover,
                                  )),
                            ),
                            Positioned(
                              right: 5,
                              top: 5,
                              child: GestureDetector(
                                onTap: onTapClose,
                                child: Container(
                                  height: 34,
                                  width: 34,
                                  alignment: Alignment.center,
                                  decoration: BoxDecoration(
                                      color: Theme.of(context)
                                          .scaffoldBackgroundColor,
                                      shape: BoxShape.circle),
                                  child: Icon(
                                    Icons.close_rounded,
                                    size: 18,
                                    color: Theme.of(context).colorScheme.error,
                                  ),
                                ),
                              ),
                            ),
                          ],
                        )
                      : Container(
                          width: 65,
                          height: 65,
                          decoration: ShapeDecoration(
                            color: Colors.white.withValues(alpha: 0.5),
                            shape: const OvalBorder(
                              side: BorderSide(
                                width: 1,
                                strokeAlign: BorderSide.strokeAlignOutside,
                                color: Colors.white,
                              ),
                            ),
                          ),
                          child: const Icon(Icons.camera_alt_outlined),
                        )),
            ),
          ),
        ],
      ),
    );
  }

  static setNetworkImage(String url, BoxFit boxFit) {
    return Image.network(
      url,
      fit: boxFit,
    );
  }

  static Future<dynamic> openModalBottomSheet(
      BuildContext buildContext, Widget children,
      {bool? isScrollControlled = true, // Default to true for better control
      bool? staticContent = false}) async {
    return await showModalBottomSheet(
        enableDrag: true, // Keep this true for draggable behavior
        showDragHandle: false, // Set to true to show the drag handle
        isScrollControlled: true, // Use the passed value
        barrierColor:
            Colors.black.withValues(alpha: 0.6), // Better way to set opacity
        backgroundColor: Colors.transparent, // Keeps the main sheet transparent
        shape: const RoundedRectangleBorder(
          borderRadius: BorderRadius.only(
            topLeft: Radius.circular(28),
            topRight: Radius.circular(28),
          ),
        ),
        context: buildContext,
        builder: (_) => Padding(
              padding: EdgeInsets.only(
                top: appContentHorizontalPadding,
               
              ),
              child: CustomModalBotomSheet(
                staticContent: staticContent,
                child: children,
              ),
            ));
  }

  static bool isValidFile(String filePath) {
    // Define allowed file extensions
    List<String> allowedExtensions = [
      'jpg',
      'jpeg',
      'png',
      'gif',
      'webp',
      'pdf',
      'doc',
      'docx',
      'xls',
      'xlsx'
    ];

    // Get file extension
    String extension = filePath.split('.').last.toLowerCase();

    // Check if the extension is allowed
    return allowedExtensions.contains(extension);
  }

  static Size getTextWidthSize(
      {required String text,
      required TextStyle textStyle,
      required BuildContext context}) {
    final TextPainter textPainter = TextPainter(
        text: TextSpan(text: text, style: textStyle),
        maxLines: 1,
        textDirection: Directionality.of(context))
      ..layout(minWidth: 0, maxWidth: double.infinity);
    return textPainter.size;
  }

  static buildVariantList(BuildContext context, OrderItems orderItm,
      {Color? backgroundColor}) {
    List attributeList = [], variantValues = [];
    if (orderItm.attrName!.isNotEmpty) {
      attributeList = orderItm.attrName!.split(',');

      variantValues = orderItm.variantValues!.split(',');
    }
    return Wrap(spacing: 8, runSpacing: 8, children: [
      if (attributeList.isNotEmpty)
        ...List.generate(attributeList.length, (index) {
          return Utils.buildVariantContainer(
              context,
              attributeList[index].trim().toString().capitalizeFirst!,
              variantValues[index],
              backgroundColor: backgroundColor);
        }),
      if (orderItm.quantity != 0)
        Utils.buildVariantContainer(
            context,
            context
                .read<SettingsAndLanguagesCubit>()
                .getTranslatedValue(labelKey: qtyKey),
            orderItm.quantity.toString(),
            backgroundColor: backgroundColor)
    ]);
  }

  static buildVariantContainer(BuildContext context, String title, String value,
      {Color? backgroundColor}) {
    return Container(
        alignment: Alignment.center,
        padding:
            const EdgeInsetsDirectional.symmetric(vertical: 2, horizontal: 2),
        width: Utils.getTextWidthSize(
                    text: '$title$value',
                    textStyle: Theme.of(context).textTheme.labelMedium!,
                    context: context)
                .width *
            1.3,
        decoration: BoxDecoration(
          color: backgroundColor ?? Theme.of(context).scaffoldBackgroundColor,
          borderRadius: BorderRadius.circular(5),
        ),
        child: Text.rich(
          textDirection: Directionality.of(context),
          TextSpan(
            children: [
              TextSpan(
                text: title,
                style: Theme.of(context).textTheme.labelMedium!.copyWith(
                      color: Theme.of(context)
                          .colorScheme
                          .secondary
                          .withValues(alpha: 0.46),
                    ),
              ),
              const TextSpan(
                text: ' ',
              ),
              TextSpan(
                text: value,
                style: Theme.of(context).textTheme.labelMedium!.copyWith(
                      color: Theme.of(context)
                          .colorScheme
                          .secondary
                          .withValues(alpha: 0.80),
                    ),
              ),
            ],
          ),
        ));
  }

  static String priceWithCurrencySymbol(
      {required double price, required BuildContext context}) {
    final CurrencySetting currencySetting = context
            .read<SettingsAndLanguagesCubit>()
            .getSettings()
            .systemSettings
            ?.currencySetting ??
        CurrencySetting();

    return NumberFormat.currency(
            symbol: currencySetting.symbol ?? "\$",
            decimalDigits: price == price.toInt() ? 0 : 2,
            name: currencySetting.name ?? "")
        .format(price);
  }

  static String formatDouble(double value) {
    if (value == value.toInt()) {
      // Check if the value is a whole number
      return value.toInt().toString(); // Return the value as an integer string
    } else {
      return value.toStringAsFixed(2); // Return the value as a double string
    }
  }

  static String formatStringToTitleCase(String input) {
    // Replace underscores with spaces
    String formattedString = input.replaceAll('_', ' ');

    // Convert to title case
    formattedString = formattedString.split(' ').map((word) {
      return word[0].toUpperCase() + word.substring(1).toLowerCase();
    }).join(' ');

    return formattedString;
  }

  static Future<String> getDowanloadFilePath(String fileName) async {
    String downloadFilePath = Platform.isAndroid
        ? (await ExternalPath.getExternalStoragePublicDirectory(
            ExternalPath.DIRECTORY_DOWNLOAD))
        : (await getApplicationDocumentsDirectory()).path;
    downloadFilePath = "$downloadFilePath/$fileName";
    return downloadFilePath;
  }

  static Future<bool> fileExists(String path) async {
    final file = File(path);
    return await file.exists();
  }

  static bool isImageUrl(String url) {
    // Convert the URL to lowercase to handle case insensitivity
    final lowerUrl = url.toLowerCase();

    // Define the image file extensions you want to check for
    final imageExtensions = [
      'jpg',
      'jpeg',
      'png',
      'gif',
      'bmp',
      'webp',
      'tiff'
    ];

    // Check if the URL ends with any of the image extensions
    return imageExtensions.any((extension) => lowerUrl.endsWith(extension));
  }

  static Future<bool> hasStoragePermissionGiven() async {
    if (Platform.isIOS) {
      bool permissionGiven = await Permission.storage.isGranted;
      if (!permissionGiven) {
        permissionGiven = (await Permission.storage.request()).isGranted;
        return permissionGiven;
      }

      return permissionGiven;
    }
    //if it is for android
    final deviceInfoPlugin = DeviceInfoPlugin();
    final androidDeviceInfo = await deviceInfoPlugin.androidInfo;
    if (androidDeviceInfo.version.sdkInt < 33) {
      bool permissionGiven = await Permission.storage.isGranted;
      if (!permissionGiven) {
        permissionGiven = (await Permission.storage.request()).isGranted;
        return permissionGiven;
      }
      return permissionGiven;
    } else {
      bool permissionGiven = await Permission.photos.isGranted;
      if (!permissionGiven) {
        permissionGiven = (await Permission.photos.request()).isGranted;
        return permissionGiven;
      }
      return permissionGiven;
    }
  }

  static Future<void> writeFileFromTempStorage(
      {required String sourcePath, required String destinationPath}) async {
    final tempFile = File(sourcePath);
    final byteData = await tempFile.readAsBytes();
    final downloadedFile = File(destinationPath);
    //write into downloaded file
    await downloadedFile.writeAsBytes(byteData.buffer
        .asUint8List(byteData.offsetInBytes, byteData.lengthInBytes));
  }

  static List getStatusTextAndColor(int status) {
    return status == 0
        ? [failKey, errorColor]
        : status == 1
            ? [successKey, successStatusColor]
            : [pendingKey, pendingStatusColor];
  }

  static List getStockTextAndColor(String status) {
    return status == '0'
        ? [outOfStockKey, errorColor]
        : status == '1'
            ? [inStockKey, successStatusColor]
            : [lowInStockKey, pendingStatusColor];
  }

  static List getProductStatusTextAndColor(String status) {
    return status == '0'
        ? [deactivedKey, errorColor]
        : status == '1'
            ? [approvedKey, successStatusColor]
            : [pendingKey, pendingStatusColor];
  }

  static List getOrderStatusTextAndColor(String status) {
    switch (status) {
      case receivedStatusType:
        return [receivedKey, receivedStatusColor];
      case processedStatusType:
        return [processedKey, processedStatusColor];
      case shippedStatusType:
        return [shippedKey, shippedStatusColor];
      case deliveredStatusType:
        return [deliveredKey, deliveredStatusColor];
      case cancelledStatusType:
        return [cancelledKey, cancelledStatusColor];
      case returnedStatusType:
        return [returnedKey, returnedStatusColor];
      case awaitingStatusType:
        return [awaitingKey, awaitingStatusColor];
      case returnedRequestPendingType:
        return [returnRequestPendingTypeKey, awaitingStatusColor];
      case returnedRequestApprovedType:
        return [returnRequestApprovedTypeKey, deliveredStatusColor];
      case returnRequestDeclineStatusType:
        return [returnRequestDeclineKey, cancelledStatusColor];
      default:
        return [status, returnedStatusColor];
    }
  }

  static List getReturnRequestStatusTextAndColor(int status) {
    switch (status) {
      case 0:
        return [pendingKey, pendingStatusColor];
      case 1:
        return [approvedKey, successStatusColor];
      case 2:
        return [rejectedKey, errorColor];
      case 3:
        return [returnedKey, returnedStatusColor];
      case 8:
        return [returnPickedupKey, deliveredStatusColor];
      default:
        return ['', grey];
    }
  }

  static Widget buildReturnRequestStatusWidget(BuildContext context, int status,
      {bool showBorder = true}) {
    final data = getReturnRequestStatusTextAndColor(status);
    return Container(
      padding:
          const EdgeInsetsDirectional.symmetric(horizontal: 6, vertical: 2),
      decoration: ShapeDecoration(
        color: data[1].withOpacity(0.12),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(borderRadius),
          side: showBorder
              ? BorderSide(width: 0.50, color: data[1])
              : BorderSide.none,
        ),
      ),
      child: CustomTextContainer(
        textKey: data[0],
        textAlign: TextAlign.center,
        maxLines: 2,
        style: Theme.of(context).textTheme.bodyMedium!.copyWith(color: data[1]),
      ),
    );
  }

  static Widget buildProfilePicture(
      BuildContext context, double size, String url,
      {BoxBorder? border,
      bool? assetImage = false,
      File? selectedFile,
      Color? outerBorderColor}) {
    return Container(
      height: size + 5,
      width: size + 5,
      padding: const EdgeInsets.all(2),
      child: Container(
        height: size,
        width: size,
        alignment: Alignment.center,
        decoration: BoxDecoration(
            shape: BoxShape.circle,
            color: Theme.of(context).colorScheme.onPrimary,
            border: border ?? const Border(),
            image: url != '' || selectedFile != null
                ? DecorationImage(
                    image: assetImage == true
                        ? FileImage(selectedFile!) as ImageProvider
                        : NetworkImage(url),
                    fit: BoxFit.cover)
                : null),
        child: url == '' && selectedFile == null
            ? Icon(
                Icons.person,
                color: Theme.of(context).colorScheme.primary,
                size: size * 0.7,
              )
            : null,
      ),
    );
  }

  static openDatePicker(BuildContext context, DateTime currentDate) {
    return showDatePicker(
        context: context,
        initialDate: DateTime.now(),
        firstDate: DateTime(1950),
        currentDate: currentDate,
        lastDate: DateTime(
            DateTime.now().year, DateTime.now().month, DateTime.now().day));
  }

  static buildStatusWidget(BuildContext context, String status,
      {bool showBorder = true}) {
    return Container(
        padding:
            const EdgeInsetsDirectional.symmetric(horizontal: 6, vertical: 2),
        decoration: ShapeDecoration(
          color: Utils.getOrderStatusTextAndColor(status)[1]
              .withValues(alpha: 0.12),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(borderRadius),
            side: showBorder
                ? BorderSide(
                    width: 0.50,
                    color: Utils.getOrderStatusTextAndColor(status)[1])
                : BorderSide.none,
          ),
        ),
        child: CustomTextContainer(
          textKey: Utils.getOrderStatusTextAndColor(status)[0],
          textAlign: TextAlign.center,
          maxLines: 2,
          style: Theme.of(context)
              .textTheme
              .bodyMedium!
              .copyWith(color: Utils.getOrderStatusTextAndColor(status)[1]),
        ));
  }

  static void hideLoader(BuildContext context) async {
    Navigator.of(context).pop();
  }

  static void showLoader(BuildContext context) async {
    showDialog(
        context: context,
        barrierDismissible: false,
        builder: (BuildContext context) {
          return const PopScope(
            canPop: false,
            child: Center(
              child: CircularProgressIndicator(),
            ),
          );
        });
  }

  static getStoreUrl(BuildContext context) {
    if (Platform.isAndroid) {
      return context
          .read<SettingsAndLanguagesCubit>()
          .getSettings()
          .systemSettings!
          .playStoreLinkForSellerApp!;
    } else if (Platform.isIOS) {
      return context
          .read<SettingsAndLanguagesCubit>()
          .getSettings()
          .systemSettings!
          .appStoreLinkForSellerApp!;
    } else {
      return "";
    }
  }

  static rateApp(BuildContext context) async {
    if (await canLaunchUrl(Uri.parse(Utils.getStoreUrl(context)))) {
      await launchUrl(Uri.parse(Utils.getStoreUrl(context)),
          mode: LaunchMode.externalApplication);
    }
  }

  static buildSearchField(
      {required BuildContext context,
      required TextEditingController controller,
      required FocusNode focusNode,
      required Function(String) onChanged}) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        IconButton(
          icon: const Icon(Icons.arrow_back_rounded),
          padding: const EdgeInsetsDirectional.all(0),
          color: Theme.of(context).colorScheme.secondary,
          onPressed: () {
            Utils.popNavigation(context);
          },
        ),
        SizedBox(
          width: MediaQuery.of(context).size.width * 0.7,
          height: 40,
          child: TextFormField(
            controller: controller,
            autofocus: true,
            focusNode: focusNode,
            textAlignVertical: TextAlignVertical.center,
            decoration: InputDecoration(
              contentPadding:
                  const EdgeInsetsDirectional.symmetric(horizontal: 15),
              hintText: context
                  .read<SettingsAndLanguagesCubit>()
                  .getTranslatedValue(labelKey: searchKey),
              hintStyle: Theme.of(context).textTheme.bodyMedium!.copyWith(
                  color: Theme.of(context)
                      .colorScheme
                      .secondary
                      .withValues(alpha: 0.67)),
            ),
            onChanged: onChanged,
          ),
        ),
      ],
    );
  }

  static loadingIndicator() {
    return const Padding(
      padding: EdgeInsets.all(8.0),
      child: Center(child: CircularProgressIndicator()),
    );
  }

  static msgWithTryAgain(BuildContext context, String msg, Function? callback,
      {String btnText = ""}) {
    if (btnText.trim().isEmpty) {
      btnText = context
          .read<SettingsAndLanguagesCubit>()
          .getTranslatedValue(labelKey: lblTryAgainKey);
    }

    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(
            msg,
            textAlign: TextAlign.center,
          ),
          if (callback != null)
            ElevatedButton(onPressed: () => callback(), child: Text(btnText))
        ],
      ),
    );
  }

  static errorDialog(BuildContext context, String errorMessage) {
    showDialog(
        context: context,
        barrierColor: Colors.transparent,
        builder: (_) => AlertDialog(
              backgroundColor: Colors.white,
              content: Container(
                child: CustomTextContainer(
                  textKey: errorMessage,
                  maxLines: null,
                  overflow: TextOverflow.visible,
                  style: Theme.of(context).textTheme.bodyMedium!.copyWith(
                        color: errorColor,
                      ),
                ),
              ),
            ));
  }

  static List getTransactionStatusTextAndColor(String status) {
    return status == 'success'
        ? [successKey, successStatusColor]
        : status == approvedKey
            ? [approvedKey, successStatusColor]
            : status == pendingKey
                ? [pendingKey, pendingStatusColor]
                : status == rejectedKey
                    ? [rejectedKey, errorColor]
                    : ["", Colors.black];
  }

  static launchURL(String url) async {
    bool? result = await canLaunchUrl(Uri.parse(url));

    if (result == true)
      await launchUrl(Uri.parse(url));
    else
      Utils.showSnackBar(message: 'Could not launch $url');
  }

  static Future openAlertDialog(
    BuildContext context, {
    required VoidCallback onTapYes,
    VoidCallback? onTapNo,
    required String message,
    String? content,
    bool? barrierDismissible,
    String? yesLabel,
    String? noLabel,
  }) async {
    return await showDialog(
        context: context,
        barrierDismissible: barrierDismissible ?? true,
        builder: (context) => AlertDialog(
              backgroundColor: Colors.white,
              insetPadding: const EdgeInsets.symmetric(horizontal: 16),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(8), // Set the radius here
              ),
              title: CustomTextContainer(
                textKey: message,
                overflow: TextOverflow.visible,
                style: Theme.of(context).textTheme.titleMedium,
                textAlign: TextAlign.center,
              ),
              content: content != null
                  ? CustomTextContainer(
                      textKey: content,
                      style: Theme.of(context).textTheme.bodyMedium!.copyWith(
                          color: Theme.of(context)
                              .colorScheme
                              .secondary
                              .withValues(alpha: 0.46)),
                      overflow: TextOverflow.visible,
                      textAlign: TextAlign.center,
                    )
                  : null,
              actions: <Widget>[
                Row(
                  mainAxisSize: MainAxisSize.min,
                  children: <Widget>[
                    Expanded(
                      child: CustomRoundedButton(
                        widthPercentage: 0.5,
                        buttonTitle: noLabel ?? noKey,
                        showBorder: true,
                        backgroundColor:
                            Theme.of(context).colorScheme.onPrimary,
                        borderColor: Theme.of(context).hintColor,
                        style: Theme.of(context).textTheme.bodyMedium!.copyWith(
                            color: Theme.of(context).colorScheme.secondary),
                        onTap: onTapNo ??
                            () {
                              Navigator.of(context).pop();
                            },
                      ),
                    ),
                    DesignConfig.defaultWidthSizedBox,
                    Expanded(
                      child: CustomRoundedButton(
                        widthPercentage: 0.5,
                        buttonTitle: yesLabel ?? yesKey,
                        showBorder: false,
                        onTap: onTapYes,
                      ),
                    ),
                  ],
                )
              ],
            ));
  }

  static buildDescription(String text) {
    return SingleChildScrollView(
      padding: const EdgeInsetsDirectional.symmetric(
          vertical: appContentHorizontalPadding),
      child: HtmlWidget(
        text,
        onErrorBuilder: (context, element, error) =>
            Text('$element error: $error'),
        onLoadingBuilder: (context, element, loadingProgress) =>
            const Center(child: CircularProgressIndicator()),
        onTapUrl: (url) {
          Utils.launchURL(url);
          return true;
        },
      ),
    );
  }

  static throwApiException(dynamic e) {
    if (e is ApiException) {
      throw ApiException(e.toString());
    } else {
      throw ApiException(defaultErrorMessageKey);
    }
  }

  static Widget buildLanguagesWidget(
      {required BuildContext context,
      required Language selectedLang,
      required Function onSelect}) {
    final langs = context.read<SettingsAndLanguagesCubit>().getLanguages();
    return SingleChildScrollView(
      padding: const EdgeInsets.symmetric(horizontal: 4),
      scrollDirection: Axis.horizontal,
      child: Row(
        mainAxisAlignment: MainAxisAlignment.start,
        children: langs.map((lang) {
          final isSelected = selectedLang == lang;
          return GestureDetector(
            onTap: () => onSelect(lang),
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
              margin: const EdgeInsets.only(right: 8),
              decoration: BoxDecoration(
                  borderRadius: BorderRadius.circular(4),
                  border: Border.all(
                      color: isSelected
                          ? Colors.transparent
                          : Theme.of(context).inputDecorationTheme.iconColor!),
                  color: isSelected
                      ? Theme.of(context).colorScheme.primary
                      : Theme.of(context).colorScheme.primaryContainer),
              child: Text(
                lang.language!.capitalizeFirst.toString(),
                style: Theme.of(context).textTheme.bodyMedium!.copyWith(
                      color: isSelected
                          ? Theme.of(context).colorScheme.onPrimary
                          : Theme.of(context).colorScheme.secondary,
                    ),
              ),
            ),
          );
        }).toList(),
      ),
    );
  }
}

extension CurrencyExtension on String {
  String withOrderSymbol({String symbol = '#'}) {
    return '$symbol$this';
  }
}
