import 'package:flutter/material.dart';

class CustomColors extends ThemeExtension<CustomColors> {
  final Color? onBoardingSecondGradientColor;
  final Color? leaderboardThirdRankColor;
  final Color? leaderboardFirstRankColor;
  final Color? leaderboardSecondRankColor;

  const CustomColors(
      {required this.onBoardingSecondGradientColor,
      required this.leaderboardFirstRankColor,
      required this.leaderboardSecondRankColor,
      required this.leaderboardThirdRankColor});
  @override
  ThemeExtension<CustomColors> copyWith({
    Color? onBoardingSecondGradientColor,
    Color? leaderboardFirstRankColor,
    Color? leaderboardSecondRankColor,
    Color? leaderboardThirdRankColor,
  }) {
    return CustomColors(
      leaderboardFirstRankColor: leaderboardFirstRankColor ?? this.leaderboardFirstRankColor,
      leaderboardSecondRankColor: leaderboardSecondRankColor ?? this.leaderboardSecondRankColor,
      leaderboardThirdRankColor: leaderboardThirdRankColor ?? this.leaderboardThirdRankColor,
        onBoardingSecondGradientColor: onBoardingSecondGradientColor ??
            this.onBoardingSecondGradientColor);
  }

  @override
  ThemeExtension<CustomColors> lerp(
      ThemeExtension<CustomColors>? other, double t) {
    if (other is! CustomColors) {
      return this;
    }
    return CustomColors(
      leaderboardThirdRankColor: Color.lerp(leaderboardThirdRankColor,
            other.leaderboardThirdRankColor, t),
      leaderboardSecondRankColor: Color.lerp(leaderboardSecondRankColor,
            other.leaderboardSecondRankColor, t),
      leaderboardFirstRankColor: Color.lerp(leaderboardFirstRankColor,
            other.leaderboardFirstRankColor, t),
        onBoardingSecondGradientColor: Color.lerp(onBoardingSecondGradientColor,
            other.onBoardingSecondGradientColor, t));
  }
}
