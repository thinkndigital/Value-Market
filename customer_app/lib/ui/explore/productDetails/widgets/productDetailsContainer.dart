import 'package:eshop_plus/commons/blocs/settingsAndLanguagesCubit.dart';
import 'package:eshop_plus/commons/product/models/product.dart';
import 'package:eshop_plus/commons/widgets/customCircularProgressIndicator.dart';
import 'package:eshop_plus/commons/widgets/customDefaultContainer.dart';
import 'package:eshop_plus/commons/widgets/customTextContainer.dart';
import 'package:eshop_plus/utils/designConfig.dart';
import 'package:eshop_plus/core/localization/labelKeys.dart';
import 'package:eshop_plus/utils/utils.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_widget_from_html/flutter_widget_from_html.dart';
import 'package:url_launcher/url_launcher.dart';

class ProductDetailsContainer extends StatefulWidget {
  final Product product;
  const ProductDetailsContainer({Key? key, required this.product})
      : super(key: key);

  @override
  _ProductDetailsContainerState createState() =>
      _ProductDetailsContainerState();
}

class _ProductDetailsContainerState extends State<ProductDetailsContainer>
    with SingleTickerProviderStateMixin {
  bool _isExpand = false;
  late AnimationController _expandController;
  late Animation<double> _animation;
  late TextStyle textStyle;
  late Product product;
  void _toggleExpanded() {
    _isExpand = !_isExpand;

    if (_isExpand) {
      _expandController.forward();
    } else {
      _expandController.reverse();
    }

    setState(() {});
  }

  @override
  void initState() {
    super.initState();
    product = widget.product;

    _expandController = AnimationController(
        vsync: this, duration: const Duration(milliseconds: 500));
    _animation = CurvedAnimation(
      parent: _expandController,
      curve: const Interval(
        0.0,
        0.4,
        curve: Curves.fastOutSlowIn,
      ),
    );
  }

  @override
  void dispose() {
    _expandController.dispose();

    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    textStyle = Theme.of(context).textTheme.bodyMedium!.copyWith(
        color: Theme.of(context).colorScheme.secondary.withValues(alpha: 0.8),
        overflow: TextOverflow.visible);
    return CustomDefaultContainer(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              CustomTextContainer(
                  textKey: productsDetailsKey,
                  style: Theme.of(context).textTheme.titleMedium),
              GestureDetector(
                onTap: _toggleExpanded,
                child: CustomTextContainer(
                    textKey: _isExpand ? "- Less" : "+ More",
                    style: Theme.of(context).textTheme.bodyMedium!.copyWith(
                        color: Theme.of(context).colorScheme.primary)),
              ),
            ],
          ),
          DesignConfig.defaultHeightSizedBox,
          Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              buildListTile(nameKey, product.name ?? ''),
              buildDescritpion(product.description ?? '', textStyle),
              SizeTransition(
                axisAlignment: 1.0,
                sizeFactor: _animation,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    buildDescritpion(product.shortDescription ?? '', textStyle),
                    if (product.productType == variableProductType &&
                        product.attributes!.isNotEmpty)
                      ...product.attributes!
                          .map((variant) => buildListTile(
                              context
                                  .read<SettingsAndLanguagesCubit>()
                                  .getTranslatedValue(
                                      labelKey: variant.attrName ?? ''),
                              variant.value ?? ''))
                          .toList(),
                    Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        buildListTile(
                            product.isCancelable == 1
                                ? cancellableTillKey
                                : cancellableKey,
                            product.isCancelable == 1
                                ? product.cancelableTill!
                                : noKey),
                        buildListTile(returnableKey,
                            product.isReturnable == 1 ? yesKey : noKey),
                        buildListTile(
                            countryOfOriginKey, product.madeIn ?? '-'),
                        if (product.brand != null && product.brand!.isNotEmpty)
                          buildListTile(brandKey, product.brandName!),
                        if (product.customFields != null &&
                            product.customFields!.isNotEmpty)
                          ...product.customFields!.map((field) {
                            String name = field['name']?.toString() ?? '';
                            String type = field['type']?.toString() ?? '';
                            var value = field['value'];

                            String displayValue = '';
                            if (value == null) {
                              displayValue = '-';
                            } else if (type == 'checkbox' && value is List) {
                              displayValue = value.join(', ');
                            } else if (type == 'color' && value != null) {
                              return Padding(
                                padding: const EdgeInsetsDirectional.symmetric(
                                    vertical: 4),
                                child: Row(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(
                                      context
                                          .read<SettingsAndLanguagesCubit>()
                                          .getTranslatedValue(labelKey: name),
                                      style: textStyle,
                                    ),
                                    const Text(' : '),
                                    Container(
                                        height: 20,
                                        width: 20,
                                        decoration: BoxDecoration(
                                            shape: BoxShape.circle,
                                            color: Utils.getColorFromHexValue(
                                                value)))
                                  ],
                                ),
                              );
                            } else if (type == 'file' &&
                                value is String &&
                                value.isNotEmpty) {
                              // Render a download link
                              return Padding(
                                padding: const EdgeInsetsDirectional.symmetric(
                                    vertical: 4),
                                child: Row(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(
                                      context
                                          .read<SettingsAndLanguagesCubit>()
                                          .getTranslatedValue(labelKey: name),
                                      style: textStyle,
                                    ),
                                    const Text(' : '),
                                    Expanded(
                                      child: InkWell(
                                        onTap: () => Utils.launchURL(value),
                                        child: Text(
                                          value.substring(
                                              value.lastIndexOf('/') + 1),
                                          maxLines: 2,
                                          overflow: TextOverflow.ellipsis,
                                          style: textStyle.copyWith(
                                            color: Theme.of(context)
                                                .colorScheme
                                                .primary,
                                            decoration:
                                                TextDecoration.underline,
                                          ),
                                        ),
                                      ),
                                    ),
                                  ],
                                ),
                              );
                            } else {
                              displayValue = value.toString();
                            }
                            return buildListTile(name, displayValue);
                          }).toList(),
                        if (product.isAttachmentRequired == 1)
                          CustomTextContainer(
                              textKey: isAttachmentRequiredNoteKey)
                      ],
                    ),
                  ],
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  buildListTile(String title, String value) {
    return Padding(
      padding: const EdgeInsetsDirectional.symmetric(vertical: 4),
      child: Text.rich(
        overflow: TextOverflow.visible,
        TextSpan(
          style: textStyle,
          children: [
            TextSpan(
              text:
                  context.read<SettingsAndLanguagesCubit>().getTranslatedValue(
                        labelKey: title,
                      ),
            ),
            const TextSpan(
              text: ' : ',
            ),
            TextSpan(
              text:
                  context.read<SettingsAndLanguagesCubit>().getTranslatedValue(
                        labelKey: value,
                      ),
            ),
          ],
        ),
      ),
    );
  }

  buildDescritpion(String description, TextStyle textStyle) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: SingleChildScrollView(
        scrollDirection: Axis.vertical,
        child: HtmlWidget(
          description,
          textStyle: textStyle,
          onTapUrl: (String? url) async {
            if (await canLaunchUrl(Uri.parse(url!))) {
              await launchUrl(Uri.parse(url));
              return true;
            } else {
              throw 'Could not launch $url';
            }
          },
          onErrorBuilder: (context, element, error) =>
              Text('$element error: $error'),
          onLoadingBuilder: (context, element, loadingProgress) =>
              CustomCircularProgressIndicator(
            indicatorColor: Theme.of(context).colorScheme.primary,
          ),
          renderMode: RenderMode.column,
        ),
      ),
    );
  }
}
