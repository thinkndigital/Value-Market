import 'package:eshoppro_deliveryboy/core/constants/themeConstants.dart';
import 'package:eshoppro_deliveryboy/commons/widgets/customTextContainer.dart';
import 'package:flutter/material.dart';

class CustomTabbar extends StatefulWidget {
final int currentPage;
final List tabTitles;
final Function onTapTitle;
const CustomTabbar(
{Key? key,
required this.currentPage,
required this.tabTitles,
required this.onTapTitle})
: super(key: key);

@override
_CustomTabbarState createState() => _CustomTabbarState();
}

class _CustomTabbarState extends State<CustomTabbar> {
@override
Widget build(BuildContext context) {
return Container(
height: 45,
width: double.maxFinite,
alignment: Alignment.center,
color: Theme.of(context).colorScheme.primaryContainer,
child: ListView(
scrollDirection: Axis.horizontal,
shrinkWrap: true,
children: List.generate(
widget.tabTitles.length,
(index) => InkWell(
onTap: () {
if (widget.currentPage != index) {
widget.onTapTitle(index);
}
},
child: Row(
children: [
Stack(
alignment: AlignmentDirectional.center,
children: [
Container(
alignment: Alignment.center,
margin: const EdgeInsets.symmetric(horizontal: 16),
child: CustomTextContainer(
textKey: widget.tabTitles[index])),
Positioned(
bottom: 0,
child: AnimatedContainer(
duration: const Duration(milliseconds: 500),
curve: Curves.fastOutSlowIn,
height: 2,
width: widget.currentPage == index
? getTextWidthSize(
text: widget.tabTitles[index],
textStyle: Theme.of(context)
.textTheme
.bodyLarge!,
context: context)
.width
: 0,
decoration: BoxDecoration(
color: Theme.of(context).colorScheme.primary,
borderRadius: BorderRadius.circular(50)),
),
)
],
),
if (index < widget.tabTitles.length - 1)
Container(
height: 15,
width: 1,
margin: EdgeInsets.symmetric(
horizontal: appContentHorizontalPadding),
color: Theme.of(context).inputDecorationTheme.iconColor,
)
],
)),
)),
);
}

Size getTextWidthSize(
{required String text,
required TextStyle textStyle,
required BuildContext context}) {
final TextPainter textPainter = TextPainter(
text: TextSpan(text: text, style: textStyle),
maxLines: 1,
textDirection: TextDirection.ltr)
..layout(minWidth: 0, maxWidth: double.infinity);
return textPainter.size;
}
}
