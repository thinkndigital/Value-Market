import 'dart:io';

import 'package:device_info_plus/device_info_plus.dart';
import 'package:value_market_delivery_boy/core/constants/appConstants.dart';
import 'package:value_market_delivery_boy/core/constants/themeConstants.dart';
import 'package:value_market_delivery_boy/commons/widgets/customModalBottomSheet.dart';
import 'package:value_market_delivery_boy/commons/widgets/dottedLineRectPainter.dart';
import 'package:value_market_delivery_boy/commons/models/systemSettings.dart';
import 'package:value_market_delivery_boy/features/auth/repositories/authRepository.dart';
import 'package:value_market_delivery_boy/core/theme/colors.dart';
import 'package:value_market_delivery_boy/commons/widgets/customLabelContainer.dart';
import 'package:value_market_delivery_boy/commons/widgets/customTextContainer.dart';
import 'package:value_market_delivery_boy/core/localization/labelKeys.dart';
import 'package:value_market_delivery_boy/main.dart';
import 'package:external_path/external_path.dart';
import 'package:file_picker/file_picker.dart';
import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart';

import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_svg/svg.dart';
import 'package:geolocator/geolocator.dart';
import 'package:get/get.dart';
import 'package:intl/intl.dart';
import 'package:path_provider/path_provider.dart';
import 'package:permission_handler/permission_handler.dart';
import 'package:url_launcher/url_launcher.dart';

import '../commons/blocs/settingsAndLanguagesCubit.dart';

class Utils {
  static msgWithTryAgain(
    BuildContext context,
    String msg,
    Function? callback, {
    String btnText = "",
  }) {
    if (btnText.trim().isEmpty) {
      btnText = context.read<SettingsAndLanguagesCubit>().getTranslatedValue(
            labelKey: lblTryAgainKey,
          );
    }

    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(msg, textAlign: TextAlign.center),
          if (callback != null)
            ElevatedButton(onPressed: () => callback(), child: Text(btnText)),
        ],
      ),
    );
  }

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

  static setSvgImage(
    String imageName, {
    Color? color,
    double? width,
    double? height,
  }) {
    return SvgPicture.asset(
      "assets/images/$imageName.svg",
      colorFilter:
          color != null ? ColorFilter.mode(color, BlendMode.srcIn) : null,
      width: width,
      height: height,
    );
  }

  static String getLottieAnimationPath(String animationFileName) {
    return "assets/animations/$animationFileName";
  }

  static Future<dynamic> showBottomSheet({
    required Widget child,
    required BuildContext context,
    bool? enableDrag,
  }) async {
    final result = Get.bottomSheet(
      child,
      enableDrag: enableDrag ?? false,
      isScrollControlled: true,
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.only(
          topLeft: Radius.circular(bottomsheetBorderRadius),
          topRight: Radius.circular(bottomsheetBorderRadius),
        ),
      ),
    );
    return result;
  }

  static loadingIndicator() {
    return const Padding(
      padding: EdgeInsets.all(8.0),
      child: Center(child: CircularProgressIndicator()),
    );
  }

  static Future<void> showSnackBar({
    required String message,
    BuildContext? context,
    TextStyle? messageTextStyle,
    SnackBarAction? action,
    Duration? duration,
    Color? backgroundColor,
  }) async {
    if (Get.isSnackbarOpen) {
      Get.back(); // closes the current snackbar immediately
    }

    Get.showSnackbar(
      GetSnackBar(
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
        duration: duration ?? snackBarDuration,
        dismissDirection: DismissDirection.none,
        snackPosition: SnackPosition.BOTTOM,
        margin: const EdgeInsets.all(10),
        borderRadius: 4,
        backgroundColor: backgroundColor ??
            Theme.of(
              navigatorKey.currentContext!,
            ).colorScheme.secondary.withValues(alpha: 0.95),
        padding: const EdgeInsets.symmetric(vertical: 12.5, horizontal: 15),
      ),
    );
  }

  static bool isUserLoggedIn() {
    return AuthRepository.getIsLogIn();
  }

  static Color? getColorFromHexValue(String hexValue) {
    if (hexValue.isEmpty) {
      return null;
    }
    final int color = int.parse(hexValue.replaceAll("#", "0xff"));
    return Color(color);
  }

  static navigateToScreen(
    BuildContext context,
    String route, {
    dynamic arguments,
    int? id,
    bool preventDuplicates = true,
    Map<String, String>? parameters,
    bool? replaceAll = false,
    bool? replacePrevious = false,
  }) {
    if (replacePrevious == true) {
    } else if (replaceAll == true) {
      Get.offAllNamed(route);
    } else {
      Get.toNamed(route, arguments: arguments);
    }
  }

  static popNavigation(BuildContext context) {
    Get.back();
  }

  static Future<List<File?>?> openFileExplorer({
    FileType? fileType,
    bool isMultiple = false,
  }) async {
    try {
      // Pick a single file
      FilePickerResult? result = await FilePicker.platform.pickFiles(
        type: fileType ?? FileType.any,
        allowMultiple: isMultiple,
      );
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
    } catch (e) {}
    return null;
  }

  static buildImageUploadWidget({
    required BuildContext context,
    required String labelKey,
    required VoidCallback onTapUpload,
    required File? file,
    bool? isFieldValueMandatory = true,
    String? imgurl,
    String? subLabel,
    required VoidCallback onTapClose,
  }) {
    Size size = MediaQuery.of(context).size;

    String filetype = "";
    if (file != null) filetype = file.path.split('.').last;

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 7.5),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          CustomLabelContainer(
            textKey: labelKey,
            isFieldValueMandatory: isFieldValueMandatory,
          ),
          if (subLabel != null)
            CustomLabelContainer(
              textKey: subLabel,
              isFieldValueMandatory: false,
              style: Theme.of(
                context,
              ).textTheme.bodySmall!.copyWith(color: Colors.red),
            ),
          const SizedBox(height: 10),
          GestureDetector(
            onTap: onTapUpload,
            child: CustomPaint(
              painter: DottedLineRectPainter(
                strokeWidth: 1.0,
                radius: 8,
                dashWidth: 4.0,
                dashSpace: 2.0,
                color: Theme.of(
                  context,
                ).colorScheme.secondary.withValues(alpha: 0.8),
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
                              image: ((file != null &&
                                          imagetypelist.contains(
                                            filetype,
                                          )) ||
                                      (imgurl != null &&
                                          imgurl.trim().isNotEmpty))
                                  ? DecorationImage(
                                      image: file != null &&
                                              imagetypelist.contains(
                                                filetype,
                                              )
                                          ? Image.file(file).image
                                          : NetworkImage(imgurl!),
                                      fit: BoxFit.cover,
                                    )
                                  : null,
                            ),
                            child: file != null &&
                                    !(imagetypelist.contains(filetype))
                                ? const Icon(Icons.attach_file_outlined)
                                : null,
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
                                  color: Theme.of(
                                    context,
                                  ).scaffoldBackgroundColor,
                                  shape: BoxShape.circle,
                                ),
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
                      ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  static setNetworkImage(String url, BoxFit boxFit) {
    return Image.network(url, fit: boxFit);
  }

  static Future<dynamic> openModalBottomSheet(
    BuildContext buildContext,
    Widget children, {
    bool? isScrollControlled,
    bool? staticContent = false,
  }) async {
    return await showModalBottomSheet(
      enableDrag: true,
      showDragHandle: false,
      isScrollControlled: true,
      barrierColor: Colors.black.withValues(alpha: 0.60),
      backgroundColor: Colors.transparent,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.only(
          topLeft: Radius.circular(28),
          topRight: Radius.circular(28),
        ),
      ),
      context: buildContext,
      builder: (_) => staticContent == true
          ? Padding(
              padding: const EdgeInsets.only(
                top: appContentHorizontalPadding,
              ),
              child: CustomModalBotomSheet(
                staticContent: true,
                child: children,
              ),
            )
          : DraggableScrollableSheet(
              initialChildSize: 0.5,
              minChildSize: 0.5,
              maxChildSize: 0.9,
              builder: (
                BuildContext context,
                ScrollController scrollController,
              ) {
                return CustomModalBotomSheet(
                  staticContent: false,
                  child: CustomScrollView(
                    controller: scrollController,
                    slivers: [
                      SliverToBoxAdapter(
                        child: Center(
                          child: Container(
                            decoration: BoxDecoration(
                              color: Theme.of(context).hintColor,
                              borderRadius: const BorderRadius.all(
                                Radius.circular(10),
                              ),
                            ),
                            height: 4,
                            width: 40,
                            margin: const EdgeInsets.symmetric(
                              vertical: 10,
                            ),
                          ),
                        ),
                      ),
                      SliverList.list(children: [children]),
                    ],
                  ),
                );
              },
            ),
    );
  }

  static Size getTextWidthSize({
    required String text,
    required TextStyle textStyle,
    required BuildContext context,
  }) {
    final TextPainter textPainter = TextPainter(
      text: TextSpan(text: text, style: textStyle),
      maxLines: 1,
      textDirection: Directionality.of(context),
    )..layout(minWidth: 0, maxWidth: double.infinity);
    return textPainter.size;
  }

  static String priceWithCurrencySymbol({
    required double price,
    required BuildContext context,
  }) {
    final CurrencySetting? currencySetting = context
            .read<SettingsAndLanguagesCubit>()
            .getSettings()
            .systemSettings
            ?.currencySetting ??
        CurrencySetting();

    return NumberFormat.currency(
      symbol: currencySetting == null ? "\$" : currencySetting.symbol ?? "\$",
      decimalDigits: price == price.toInt() ? 0 : 2,
      name: currencySetting == null ? null : currencySetting.name ?? "",
    ).format(price);
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
            ExternalPath.DIRECTORY_DOWNLOAD,
          ))
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
      'tiff',
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

  static Future<void> writeFileFromTempStorage({
    required String sourcePath,
    required String destinationPath,
  }) async {
    final tempFile = File(sourcePath);
    final byteData = await tempFile.readAsBytes();
    final downloadedFile = File(destinationPath);
    //write into downloaded file
    await downloadedFile.writeAsBytes(
      byteData.buffer.asUint8List(
        byteData.offsetInBytes,
        byteData.lengthInBytes,
      ),
    );
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
      case returnRequestPendingStatusType:
        return [returnRequestPendingKey, returnRequestPendingStatusColor];
      case returnRequestApproveStatusType:
        return [returnRequestApproveKey, returnRequestApproveStatusColor];
      case returnPickedupStatusType:
        return [returnPickedupKey, Colors.grey];
      default:
        return [null, null];
    }
  }

  static Widget buildProfilePicture(
    BuildContext context,
    double size,
    String url, {
    BoxBorder? border,
    bool? assetImage = false,
    File? selectedFile,
    Color? outerBorderColor,
  }) {
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
                  fit: BoxFit.cover,
                )
              : null,
        ),
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

  static getStoreUrl(BuildContext context) {
    if (Platform.isAndroid) {
      return context
          .read<SettingsAndLanguagesCubit>()
          .getSettings()
          .systemSettings!
          .playStoreLinkForDeliveryBoyApp!;
    } else if (Platform.isIOS) {
      return context
          .read<SettingsAndLanguagesCubit>()
          .getSettings()
          .systemSettings!
          .appStoreLinkForDeliveryBoyApp!;
    } else {
      return "";
    }
  }

  // final storeUrl = Platform.isAndroid ? androidLink : iosLink;
  static rateApp(BuildContext context) async {
    if (await canLaunchUrl(Uri.parse(Utils.getStoreUrl(context)))) {
      await launchUrl(
        Uri.parse(Utils.getStoreUrl(context)),
        mode: LaunchMode.externalApplication,
      );
    }
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
            style: Theme.of(
              context,
            ).textTheme.bodyMedium!.copyWith(color: errorColor),
          ),
        ),
      ),
    );
  }

  static launchURL(String url) async {
    bool? result = await canLaunchUrl(Uri.parse(url));

    if (result == true)
      await launchUrl(Uri.parse(url));
    else
      Utils.showSnackBar(message: 'Could not launch $url');
  }

  static openAlertDialog(
    BuildContext context, {
    required VoidCallback onTapYes,
    VoidCallback? onTapNo,
    required String message,
    String? content,
    bool? barrierDismissible,
    String? yesLabel,
    String? noLabel,
  }) async {
    return await showCupertinoDialog(
      context: context,
      barrierDismissible: barrierDismissible ?? true,
      builder: (context) => CupertinoAlertDialog(
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
                      color: Theme.of(
                        context,
                      ).colorScheme.secondary.withValues(alpha: 0.46),
                    ),
                overflow: TextOverflow.visible,
                textAlign: TextAlign.center,
              )
            : null,
        actions: <Widget>[
          CupertinoDialogAction(
            onPressed: onTapNo ??
                () {
                  Navigator.of(context).pop();
                },
            child: CustomTextContainer(textKey: noLabel ?? noKey),
          ),
          CupertinoDialogAction(
            isDestructiveAction: true, // Highlight as destructive (exit action)
            onPressed: onTapYes,
            child: CustomTextContainer(textKey: yesLabel ?? yesKey),
          ),
        ],
      ),
    );
  }

  static openDatePicker(BuildContext context, DateTime currentDate) {
    return showDatePicker(
      context: context,
      initialDate: DateTime.now(),
      firstDate: DateTime(1950),
      currentDate: currentDate,
      //DateTime.now() - not to allow to choose before today.
      lastDate: DateTime(
        DateTime.now().year,
        DateTime.now().month,
        DateTime.now().day,
      ),
    );
  }

  static List getCollectionStatusTextAndColor(String type) {
    return type.trim().toLowerCase() == "collected"
        ? [collectedKey, cancelledStatusColor]
        : type.trim().toLowerCase() == "received"
            ? [receivedKey, deliveredStatusColor]
            : [type, Colors.black];
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

  static void hideLoader(BuildContext context) async {
    Navigator.of(context).pop();
  }

  static Future<Position?> getCurrentLocation() async {
    bool serviceEnabled;
    LocationPermission permission;

    // Check if location services are enabled
    serviceEnabled = await Geolocator.isLocationServiceEnabled();
    if (!serviceEnabled) {
      // Location services are not enabled, you can show a message to the user
      return Future.error('Location services are disabled.');
    }

    // Check for location permissions
    permission = await Geolocator.checkPermission();
    if (permission == LocationPermission.denied) {
      permission = await Geolocator.requestPermission();
      if (permission == LocationPermission.denied) {
        // Permissions are denied, next time you could ask again
        return Future.error('Location permissions are denied.');
      }
    }

    if (permission == LocationPermission.deniedForever) {
      // Permissions are denied forever, handle appropriately
      return Future.error(
        'Location permissions are permanently denied, we cannot request permissions.',
      );
    }

    // When permissions are granted, retrieve the current position
    return await Geolocator.getCurrentPosition(
      locationSettings: LocationSettings(accuracy: LocationAccuracy.high),
    );
  }

  static void showLoader(BuildContext context) async {
    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (BuildContext context) {
        return const PopScope(
          canPop: false,
          child: Center(child: CircularProgressIndicator()),
        );
      },
    );
  }

  static buildVariantContainer(
    BuildContext context,
    String title,
    String value,
  ) {
    return Container(
      alignment: Alignment.center,
      padding: const EdgeInsetsDirectional.symmetric(
        vertical: 2,
        horizontal: 2,
      ),
      width: Utils.getTextWidthSize(
            text: '$title$value',
            textStyle: Theme.of(context).textTheme.labelMedium!,
            context: context,
          ).width *
          1.3,
      decoration: BoxDecoration(
        color: Theme.of(context).scaffoldBackgroundColor,
        borderRadius: BorderRadius.circular(5),
      ),
      child: Text.rich(
        textDirection: Directionality.of(context),
        TextSpan(
          children: [
            TextSpan(
              text: title,
              style: Theme.of(context).textTheme.labelMedium!.copyWith(
                    color: Theme.of(
                      context,
                    ).colorScheme.secondary.withValues(alpha: 0.46),
                  ),
            ),
            const TextSpan(text: ' '),
            TextSpan(
              text: value,
              style: Theme.of(context).textTheme.labelMedium!.copyWith(
                    color: Theme.of(
                      context,
                    ).colorScheme.secondary.withValues(alpha: 0.80),
                  ),
            ),
          ],
        ),
      ),
    );
  }
}
