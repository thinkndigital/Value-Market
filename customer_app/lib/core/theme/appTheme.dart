import 'package:value_market_customer/core/constants/themeConstants.dart';
import 'package:value_market_customer/core/theme/colors.dart';
import 'package:value_market_customer/commons/models/store.dart';
import 'package:value_market_customer/utils/utils.dart';
import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

class AppTheme {
  static ThemeData getThemeData(BuildContext context, Store defaultStore) =>
      Theme.of(context).copyWith(
        textTheme: GoogleFonts.rubikTextTheme(Theme.of(context).textTheme),
        secondaryHeaderColor: secondaryColor,
        scaffoldBackgroundColor:
            Utils.getColorFromHexValue(defaultStore.backgroundColor) ??
                const Color(0xFFF5F8F9),
        shadowColor: const Color(0x3F000000),
        hintColor: secondaryColor.withValues(alpha: 0.67),
        appBarTheme: const AppBarTheme(
          backgroundColor: Colors.white,
        ),
        
        iconTheme: IconThemeData(color: secondaryColor),
        dividerColor: borderColor.withValues(alpha: 0.4),
        inputDecorationTheme: InputDecorationTheme(
          iconColor: borderColor.withValues(alpha: 0.4),
          border: OutlineInputBorder(
            borderSide: BorderSide(color: borderColor.withValues(alpha: 0.4)),
            borderRadius: const BorderRadius.all(Radius.circular(borderRadius)),
          ),
          enabledBorder: OutlineInputBorder(
              borderSide: BorderSide(color: borderColor.withValues(alpha: 0.4)),
              borderRadius: BorderRadius.circular(borderRadius)),
          errorBorder: OutlineInputBorder(
              borderSide: BorderSide(color: errorColor),
              borderRadius: BorderRadius.circular(borderRadius)),
          focusedErrorBorder: OutlineInputBorder(
              borderSide: BorderSide(color: borderColor.withValues(alpha: 0.4)),
              borderRadius: BorderRadius.circular(borderRadius)),
          focusedBorder: OutlineInputBorder(
              borderSide: BorderSide(color: borderColor.withValues(alpha: 0.4)),
              borderRadius: BorderRadius.circular(borderRadius)),
          disabledBorder: OutlineInputBorder(
              borderSide:
                  BorderSide(color: secondaryColor.withValues(alpha: 0.67)),
              borderRadius: BorderRadius.circular(borderRadius)),
        ),
        bottomNavigationBarTheme: const BottomNavigationBarThemeData(
            backgroundColor: Colors.white, elevation: 1),
        colorScheme: ColorScheme.fromSeed(
          seedColor: Utils.getColorFromHexValue(defaultStore.primaryColor) ??
              primaryColor,
          primary: Utils.getColorFromHexValue(defaultStore.primaryColor) ??
              primaryColor,
          primaryContainer: Utils.getColorFromHexValue('#FFFFFF'),
          surface: Utils.getColorFromHexValue(defaultStore.backgroundColor),
          secondary: Utils.getColorFromHexValue(defaultStore.secondaryColor) ??
              secondaryColor,
          shadow: const Color(0x3F000000),
          error: errorColor,
          
        ),
      );
}
