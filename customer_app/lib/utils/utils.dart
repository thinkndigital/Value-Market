import 'dart:io';

import 'package:cached_network_image/cached_network_image.dart';
import 'package:device_info_plus/device_info_plus.dart';
import 'package:value_market_customer/core/api/apiService.dart';
import 'package:value_market_customer/core/configs/appConfig.dart';
import 'package:value_market_customer/core/constants/themeConstants.dart';
import 'package:value_market_customer/utils/designConfig.dart';
import 'package:external_path/external_path.dart';
import 'package:value_market_customer/main.dart';
import 'package:value_market_customer/core/routes/routes.dart';
import 'package:value_market_customer/ui/auth/cubits/authCubit.dart';

import 'package:value_market_customer/commons/blocs/settingsAndLanguagesCubit.dart';
import 'package:value_market_customer/ui/profile/address/models/address.dart';

import 'package:value_market_customer/ui/favorites/models/offlineFavorite.dart';
import 'package:value_market_customer/commons/product/models/productMinMaxPrice.dart';
import 'package:value_market_customer/commons/models/systemSettings.dart';
import 'package:value_market_customer/ui/auth/repositories/authRepository.dart';
import 'package:value_market_customer/core/theme/colors.dart';

import 'package:value_market_customer/commons/widgets/customTextButton.dart';
import 'package:value_market_customer/commons/widgets/customTextContainer.dart';

import 'package:value_market_customer/core/api/apiEndPoints.dart';
import 'package:value_market_customer/core/constants/hiveConstants.dart';
import 'package:value_market_customer/core/localization/labelKeys.dart';
import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_svg/svg.dart';
import 'package:get/get.dart';
import 'package:get/route_manager.dart';
import 'package:hive/hive.dart';
import 'package:path_provider/path_provider.dart';
import 'package:permission_handler/permission_handler.dart';
import 'package:shimmer/shimmer.dart';
import 'package:url_launcher/url_launcher.dart';

import '../commons/widgets/customModalBottomSheet.dart';

import 'package:intl/intl.dart';

import '../commons/widgets/customRoundedButton.dart';

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

  static String getLottieAnimationPath(String imageName) {
    return "assets/animation/$imageName";
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

  static Future<dynamic> showBottomSheet(
      {required Widget child,
      required BuildContext context,
      bool? enableDrag}) async {
    final result = Get.bottomSheet(child,
        enableDrag: enableDrag ?? false,
        isScrollControlled: true,
        backgroundColor: Theme.of(context).scaffoldBackgroundColor,
        shape: const RoundedRectangleBorder(
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
      Duration? duration,
      Color? backgroundColor}) async {
    if (Get.isSnackbarOpen) {
      Get.back(); // closes the current snackbar immediately
    }
    if (lastSnackbarMessage != message) {
      lastSnackbarMessage = message;
      Get.showSnackbar(GetSnackBar(
          snackbarStatus: (status) {
            if (status == SnackbarStatus.CLOSED) {
              lastSnackbarMessage = '';
            }
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
              Theme.of(navigatorKey.currentContext!).colorScheme.secondary,
          padding: const EdgeInsets.symmetric(vertical: 12.5, horizontal: 15)));
    }
  }

  static String fromTime(
      {required TimeOfDay timeOfDay, required BuildContext context}) {
    return timeOfDay.format(context);
  }

  static bool isUserLoggedIn() {
    return AuthRepository.getIsLogIn();
  }

  static Map<String, dynamic> getParamsForVerifyUser(BuildContext context) {
    return context.read<AuthCubit>().getUserDetails().type == phoneLoginType
        ? {
            ApiURL.mobileApiKey:
                context.read<AuthCubit>().getUserDetails().mobile
          }
        : {
            ApiURL.emailApiKey: context.read<AuthCubit>().getUserDetails().email
          };
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
    final int? color = int.tryParse(hexValue.replaceAll("#", "0xff"));
    if (color == null) {
      return Colors.white;
    }
    return Color(color);
  }

  static Future<dynamic>? navigateToScreen(BuildContext context, String route,
      {dynamic arguments,
      int? id,
      bool preventDuplicates = true,
      Map<String, String>? parameters,
      bool? replaceAll = false,
      bool? replacePrevious = false}) async {
    // we don't want to prevent duplicate for subcategory screen
    //bcoz for nested categories,it can open same screen for its children
    if (route == Routes.subCategoryScreen) {
      preventDuplicates = false;
    }
    if (replacePrevious == true) {
      return await Get.offNamed(route,
          arguments: arguments, preventDuplicates: preventDuplicates);
    } else if (replaceAll == true) {
      Get.offNamedUntil(route, (route) => false);
    } else {
      return await Get.toNamed(route,
          arguments: arguments, preventDuplicates: preventDuplicates);
    }
  }

  static Widget getSvgImage(String url,
      {double? width, double? height, BoxFit? boxFit, Color? color}) {
    return SvgPicture.network(
      url,
      colorFilter:
          color != null ? ColorFilter.mode(color, BlendMode.srcIn) : null,
      width: width,
      height: height,
      fit: boxFit ?? BoxFit.cover,
      placeholderBuilder: (context) {
        return Shimmer.fromColors(
          baseColor: Colors.grey[300]!,
          highlightColor: Colors.grey[100]!,
          child: Container(
            width: width,
            height: height,
            color: Colors.white,
          ),
        );
      },
    );
  }

  static popNavigation(BuildContext context, {dynamic result}) {
    Get.back(result: result);
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

  static String getDayOfMonthSuffix(int day) {
    if (day >= 11 && day <= 13) {
      return 'th';
    }
    switch (day % 10) {
      case 1:
        return 'st';
      case 2:
        return 'nd';
      case 3:
        return 'rd';
      default:
        return 'th';
    }
  }

  static Future<File?> openFileExplorer() async {
    try {
      // Pick a single file
      FilePickerResult? result = await FilePicker.platform.pickFiles();

      if (result != null) {
        return File(result.files.single.path!);
      }
      return null;
      // Use the file path
    } catch (e) {}
    return null;
  }

  static Future<bool?> showBackDialog(BuildContext context) {
    return showDialog<bool>(
      context: context,
      builder: (BuildContext context) {
        return AlertDialog(
          title: const CustomTextContainer(textKey: areYouSureKey),
          content: const CustomTextContainer(textKey: areYouSureToExitAppKey),
          actions: <Widget>[
            CustomTextButton(
                buttonTextKey: noKey,
                onTapButton: Utils.popNavigation(context)),
            CustomTextButton(buttonTextKey: exitKey, onTapButton: () => exit(0))
          ],
        );
      },
    );
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
              padding: const EdgeInsets.only(top: appContentHorizontalPadding),
              child: CustomModalBotomSheet(
                staticContent: staticContent,
                child: children,
              ),
            ));
  }

  static searchIcon(BuildContext context) {
    return IconButton(
        onPressed: () => Utils.navigateToScreen(context, Routes.searchScreen),
        icon: Icon(
          Icons.search,
          color: Theme.of(context).colorScheme.secondary,
        ));
  }

  static favoriteIcon(BuildContext context) {
    return IconButton(
        padding: EdgeInsets.zero,
        onPressed: () => Utils.navigateToScreen(context, Routes.favoriteScreen),
        icon: Icon(
          Icons.favorite_border,
          color: Theme.of(context).colorScheme.secondary,
        ));
  }

  static cartIcon(BuildContext context) {
    return IconButton(
      onPressed: () => Utils.navigateToScreen(context, Routes.cartScreen),
      icon: Utils.setSvgImage(
        'cart_for_icon',
        color: Theme.of(context).colorScheme.secondary,
      ),
    );
  }

  static getStoreUrl(BuildContext context) {
    if (Platform.isAndroid) {
      return context
          .read<SettingsAndLanguagesCubit>()
          .getSettings()
          .systemSettings!
          .playStoreLinkForCustomerApp!;
    } else if (Platform.isIOS) {
      return context
          .read<SettingsAndLanguagesCubit>()
          .getSettings()
          .systemSettings!
          .appStoreLinkForCustomerApp!;
    } else {
      return "";
    }
  }

// final storeUrl = Platform.isAndroid ? androidLink : iosLink;
  static rateApp(BuildContext context) async {
    if (await canLaunchUrl(Uri.parse(Utils.getStoreUrl(context)))) {
      await launchUrl(Uri.parse(Utils.getStoreUrl(context)),
          mode: LaunchMode.externalApplication);
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

  static openDatePicker(BuildContext context, DateTime currentDate) {
    return showDatePicker(
        context: context,
        initialDate: DateTime.now(),
        firstDate: DateTime(1950),
        currentDate: currentDate,
        lastDate: DateTime(
            DateTime.now().year, DateTime.now().month, DateTime.now().day));
  }

  static launchURL(String url) async {
    bool? result = await canLaunchUrl(Uri.parse(url));

    if (result == true)
      await launchUrl(Uri.parse(url));
    else
      Utils.showSnackBar(message: 'Could not launch $url');
  }

  static buildVariantContainer(
      BuildContext context, String title, String value) {
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
        CurrencySetting(id: 1, code: 'USD', symbol: '\$');

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

  ///[Filter ratings if you change this make sure to change in the getFilterRatingsValues()]
  static List<String> getFilterRatings({required BuildContext context}) {
    return [
      "4 \u{2605} ${context.read<SettingsAndLanguagesCubit>().getTranslatedValue(labelKey: andAboveKey)}",
      "3 \u{2605} ${context.read<SettingsAndLanguagesCubit>().getTranslatedValue(labelKey: andAboveKey)}",
      "2 \u{2605} ${context.read<SettingsAndLanguagesCubit>().getTranslatedValue(labelKey: andAboveKey)}",
      "1 \u{2605} ${context.read<SettingsAndLanguagesCubit>().getTranslatedValue(labelKey: andAboveKey)}",
    ];
  }

  ///[As mentioned in the getFilterRatings() this will be the values to be passed in api]
  static List<String> getFilterRatingsValues({required BuildContext context}) {
    return [
      "4",
      "3",
      "2",
      "1",
    ];
  }

  ///[Filter discounts if you change this make sure to change in the getFilterDiscountsValues()]
  static List<String> getFilterDiscounts({required BuildContext context}) {
    return [
      "30% ${context.read<SettingsAndLanguagesCubit>().getTranslatedValue(labelKey: orMoreKey)}",
      "40% ${context.read<SettingsAndLanguagesCubit>().getTranslatedValue(labelKey: orMoreKey)}",
      "50% ${context.read<SettingsAndLanguagesCubit>().getTranslatedValue(labelKey: orMoreKey)}",
      "60% ${context.read<SettingsAndLanguagesCubit>().getTranslatedValue(labelKey: orMoreKey)}",
      "70% ${context.read<SettingsAndLanguagesCubit>().getTranslatedValue(labelKey: orMoreKey)}",
    ];
  }

  ///[As mentioned in the getFilterDiscounts() this will be the values to be passed in api]
  static List<String> getFilterDiscountsValues(
      {required BuildContext context}) {
    return [
      "30",
      "40",
      "50",
      "60",
      "70",
    ];
  }

  static List getTransactionStatusTextAndColor(String status) {
    return status == 'success'
        ? [successKey, successStatusColor]
        : status == 'awaiting'
            ? [pendingKey, pendingStatusColor]
            : status == 'fail'
                ? [failKey, errorColor]
                : status == approvedKey
                    ? [approvedKey, successStatusColor]
                    : status == pendingKey
                        ? [pendingKey, pendingStatusColor]
                        : status == rejectedKey
                            ? [rejectedKey, errorColor]
                            : ["", Colors.black];
  }

  static List getTicketStatusTextAndColor(String status) {
    //1 -> pending, 2 -> opened, 3 -> resolved, 4 -> closed, 5 -> reopened
    if (status == '1') {
      return [pendingKey, pendingStatusColor];
    } else if (status == '2') {
      return [openedKey, Colors.cyan];
    } else if (status == '3') {
      return [resolvedKey, successStatusColor];
    } else if (status == '5') {
      return [reopenedKey, Colors.cyan];
    } else if (status == '4') {
      return [closedKey, errorColor];
    }
    return ['', ''];
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

  static errorDialog(BuildContext context, String errorMessage) {
    showDialog(
        context: context,
        builder: (_) => AlertDialog(
              content: SizedBox(
                height: 200,
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
    return await showDialog(
        context: context,
        barrierDismissible: barrierDismissible ?? true,
        builder: (context) => AlertDialog(
              backgroundColor: Colors.white,
              insetPadding: const EdgeInsets.symmetric(horizontal: 20),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(8),
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
                    CustomRoundedButton(
                      widthPercentage: null,
                      buttonTitle: noLabel ?? noKey,
                      showBorder: true,
                      backgroundColor: Theme.of(context).colorScheme.onPrimary,
                      borderColor: Theme.of(context).hintColor,
                      style: Theme.of(context).textTheme.bodyMedium!.copyWith(
                          color: Theme.of(context).colorScheme.secondary),
                      onTap: onTapNo ??
                          () {
                            Navigator.of(context).pop();
                          },
                    ),
                    DesignConfig.defaultWidthSizedBox,
                    CustomRoundedButton(
                      widthPercentage: null,
                      buttonTitle: yesLabel ?? yesKey,
                      showBorder: false,
                      onTap: onTapYes,
                    ),
                  ],
                )
              ],
            ));
  }

  static setNetworkImage(
      {required String url,
      BoxFit boxFit = BoxFit.fill,
      double width = double.infinity,
      double height = double.infinity,
      double borderRadius = 4}) {
    return Container(
        width: width,
        height: height,
        alignment: Alignment.center,
        decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(borderRadius),
            image: DecorationImage(
                fit: boxFit, image: CachedNetworkImageProvider(url))));
  }

  static List<ProductMinMaxPrice> calculatePriceRanges(
      {required double minPrice, required double maxPrice}) {
    int steps = 6;
    List<ProductMinMaxPrice> priceRanges = [];
    double stepSize = (maxPrice - minPrice) / steps;

    for (int i = 0; i < steps; i++) {
      double rangeMinPrice = minPrice + (i * stepSize);
      double rangeMaxPrice = rangeMinPrice + stepSize;

      priceRanges.add(ProductMinMaxPrice(
        minPrice: rangeMinPrice,
        maxPrice: rangeMaxPrice,
      ));
    }

    priceRanges.first = priceRanges.first.copyWith(minPrice: -1);
    priceRanges.last = priceRanges.last.copyWith(maxPrice: -1);

    return priceRanges;
  }

  static hexToColor(String hexString) {
    final buffer = StringBuffer();
    if (hexString.length == 6 || hexString.length == 7) buffer.write('ff');
    buffer.write(hexString.replaceFirst('#', ''));
    return Color(int.parse(buffer.toString(), radix: 16));
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

  static Future<void> syncFavoritesToUser(BuildContext context) async {
    var box = await Hive.openBox(favoritesBoxKey);
    List<OfflineFavorite> items =
        box.values.map((fav) => OfflineFavorite.fromMap(fav)).toList();
    // Make API calls to add each item to the user's cart
    for (var item in items) {
      try {
        // Call the API here
        await Api.post(
            url: ApiURL.addFavoriteProduct,
            body: item.type == 'seller'
                ? {
                    ApiURL.isSellerApiKey: 1,
                    ApiURL.sellerIdApiKey: item.id,
                  }
                : {
                    ApiURL.isSellerApiKey: 0,
                    ApiURL.productIdApiKey: item.id,
                    ApiURL.productTypeApiKey: item.productType,
                  },
            useAuthToken: true);
      } catch (e) {
        continue;
      }
    }

    // Clear the offline cart once synced
    await box.clear();
  }

  static Future<void> openHiveBoxIfNeeded(String boxName) async {
    if (!Hive.isBoxOpen(boxName)) {
      await Hive.openBox(boxName);
    }
  }

  static Future<bool> requestMicrophonePermission(BuildContext context) async {
    PermissionStatus microphoneStatus = await Permission.microphone.request();
    var speechStatus = await Permission.speech.status; // For speech recognition

    if (microphoneStatus.isDenied || speechStatus.isDenied) {
      // Request both permissions
      Map<Permission, PermissionStatus> statuses = await [
        Permission.microphone,
        Permission.speech,
      ].request();

      if (statuses[Permission.microphone]!.isGranted &&
          statuses[Permission.speech]!.isGranted) {
        return true;
      } else {
        Utils.showSnackBar(
            message: speechPermissionsDeniedKey, context: context);
        return false;
      }
    } else if (microphoneStatus.isPermanentlyDenied ||
        speechStatus.isPermanentlyDenied) {
      Utils.showSnackBar(
          message: microphonePermissionIsDisabledKey, context: context);
      openAppSettings(); // Open app settings
    } else {
      return true;
    }
    return false;
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

  static Widget getAddressWidget(BuildContext context, Address address) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        CustomTextContainer(
            textKey: address.name ?? '',
            style: Theme.of(context).textTheme.titleMedium),
        CustomTextContainer(
          textKey:
              '${address.address},\n${address.area}, ${address.city}, ${address.state} ${address.pincode}',
          maxLines: null,
          overflow: TextOverflow.visible,
          style: Theme.of(context).textTheme.bodyMedium!.copyWith(
              color: Theme.of(context)
                  .colorScheme
                  .secondary
                  .withValues(alpha: 0.8)),
        ),
        CustomTextContainer(
          textKey:
              '${context.read<SettingsAndLanguagesCubit>().getTranslatedValue(labelKey: phoneNumberKey)} : ${address.mobile ?? ""}',
          style: Theme.of(context).textTheme.bodyMedium!.copyWith(
              color: Theme.of(context)
                  .colorScheme
                  .secondary
                  .withValues(alpha: 0.8)),
        ),
      ],
    );
  }

  static bool isDemoUser(BuildContext context) {
    return isDemoApp &&
        context.read<AuthCubit>().getUserDetails().type == phoneLoginType &&
        context.read<AuthCubit>().getUserDetails().mobile ==
            defaultPhoneNumber &&
        context.read<AuthCubit>().getUserDetails().id == 423;
  }
}
