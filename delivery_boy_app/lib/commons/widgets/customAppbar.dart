import 'package:eshoppro_deliveryboy/core/constants/themeConstants.dart';
import 'package:eshoppro_deliveryboy/commons/widgets/customTextContainer.dart';
import 'package:eshoppro_deliveryboy/utils/designConfig.dart';
import 'package:eshoppro_deliveryboy/utils/utils.dart';
import 'package:flutter/material.dart';

class CustomAppbar extends StatelessWidget implements PreferredSizeWidget {
final Function? onBackButtonTap;
final String titleKey;
final bool showBackButton;
final bool? centerTitle;
final Widget? trailingWidget;
final double? elevation;
final Color? backgroundColor;
final Widget? leadingWidget;

const CustomAppbar(
{super.key,
this.showBackButton = true,
required this.titleKey,
this.onBackButtonTap,
this.elevation,
this.trailingWidget,
this.backgroundColor,
this.leadingWidget,
this.centerTitle});

Widget _buildAppBarTitle(BuildContext context) {
return Expanded(
child: Padding(
padding: EdgeInsetsDirectional.only(
start: showBackButton ? 8 : 12,
),
child: CustomTextContainer(
maxLines: 1,
overflow: TextOverflow.ellipsis,
textKey: titleKey,
style: Theme.of(context).textTheme.titleLarge!.copyWith(
fontWeight: FontWeight.w400,
),
),
),
);
}

@override
Widget build(BuildContext context) {
return Container(
decoration: BoxDecoration(
color: backgroundColor ?? Theme.of(context).appBarTheme.backgroundColor,
boxShadow: elevation == 0 ? null : DesignConfig.appShadow,
),
height: kToolbarHeight + MediaQuery.of(context).padding.top,
padding: EdgeInsetsDirectional.only(
top: MediaQuery.of(context).padding.top,
end: appContentHorizontalPadding,
),
width: MediaQuery.of(context).size.width,
alignment: Alignment.center,
child: Row(
crossAxisAlignment: CrossAxisAlignment.center,
mainAxisSize: MainAxisSize.min,
mainAxisAlignment: MainAxisAlignment.start,
children: [
if (showBackButton)
IconButton(
icon: const Icon(Icons.arrow_back_rounded),
padding: const EdgeInsetsDirectional.all(0),
color: Theme.of(context).colorScheme.secondary,
onPressed: () {
if (onBackButtonTap != null) {
onBackButtonTap!();
} else {
Utils.popNavigation(context);
}
},
),
if (leadingWidget != null) leadingWidget!,
_buildAppBarTitle(context),
if (trailingWidget != null)
Align(alignment: Alignment.center, child: trailingWidget!),
],
),
);
}

@override
Size get preferredSize => const Size.fromHeight(kToolbarHeight);
}
