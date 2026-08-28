import 'package:eshopplus_seller/core/constants/themeConstants.dart';
import 'package:eshopplus_seller/features/profile/faq/blocs/deleteFaqCubit.dart';
import 'package:eshopplus_seller/features/profile/faq/blocs/faqCubit.dart';
import 'package:eshopplus_seller/features/profile/faq/models/faq.dart';
import 'package:eshopplus_seller/commons/widgets/customCircularProgressIndicator.dart';
import 'package:eshopplus_seller/commons/widgets/customTextContainer.dart';

import 'package:eshopplus_seller/core/localization/labelKeys.dart';
import 'package:eshopplus_seller/utils/utils.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

class ProductFaqWidget extends StatefulWidget {
  final bool? fromProductScreen;
  final FAQ faq;
  final FAQCubit faqCubit;

  const ProductFaqWidget(
      {super.key,
      required this.faq,
      required this.faqCubit,
      this.fromProductScreen = false});

  @override
  State<ProductFaqWidget> createState() => _ProductFaqWidgetState();
}

class _ProductFaqWidgetState extends State<ProductFaqWidget> {
  @override
  Widget build(BuildContext context) {
    return BlocProvider(
      create: (context) => DeleteFAQCubit(),
      child: BlocListener<DeleteFAQCubit, DeleteFAQState>(
        listener: (context, state) {
          if (state is DeleteFAQSuccess) {
            if (widget.faqCubit.state is FAQFetchSuccess) {
              widget.faqCubit.emisSuccessState(
                  (widget.faqCubit.state as FAQFetchSuccess)
                      .faqs
                      .where((element) => element.id != state.faqId.toString())
                      .toList());
            }

            Utils.showSnackBar(
              message: state.successMessage,
              
            );
          } else if (state is DeleteFAQFailure) {
            Utils.showSnackBar(message: state.errorMessage);
          }
        },
        child: Container(
          decoration:
              BoxDecoration(border: Border.all(color: Colors.transparent)),
          padding: const EdgeInsetsDirectional.symmetric(
              horizontal: appContentHorizontalPadding),
          child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                if (widget.fromProductScreen == false) ...[
                  CustomTextContainer(
                    textKey: widget.faq.productName ?? "",
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: Theme.of(context).textTheme.titleSmall!,
                  ),
                  const SizedBox(height: 4),
                ],
                Text.rich(
                  TextSpan(
                    style: Theme.of(context).textTheme.titleSmall!,
                    children: [
                      const TextSpan(text: 'Q'),
                      const TextSpan(
                        text: ' : ',
                      ),
                      TextSpan(
                        text: widget.faq.question,
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 4),
                Text.rich(
                  TextSpan(
                    style: Theme.of(context).textTheme.bodyMedium!,
                    children: [
                      const TextSpan(text: 'A'),
                      const TextSpan(
                        text: ' : ',
                      ),
                      TextSpan(
                        text: widget.faq.answer,
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 4),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Expanded(
                      child: Text.rich(
                        TextSpan(
                          style: Theme.of(context)
                              .textTheme
                              .bodySmall!
                              .copyWith(
                                  color: Theme.of(context)
                                      .colorScheme
                                      .secondary
                                      .withValues(alpha: 0.80)),
                          children: [
                            TextSpan(text: widget.faq.answeredBy),
                            const TextSpan(
                              text: ' | ',
                            ),
                            TextSpan(
                              text: widget.faq.createdAt,
                            ),
                          ],
                        ),
                      ),
                    ),
                    buildDeleteButton(),
                  ],
                ),
              ]),
        ),
      ),
    );
  }

  buildDeleteButton() {
    return BlocBuilder<DeleteFAQCubit, DeleteFAQState>(
      builder: (context, state) {
        return IconButton(
          padding: EdgeInsets.zero,
          visualDensity: const VisualDensity(vertical: -4, horizontal: -4),
          icon: state is DeleteFAQProgress &&
                  state.faqId.toString() == widget.faq.id
              ? CustomCircularProgressIndicator(
                  indicatorColor: Theme.of(context).colorScheme.error,
                )
              : Icon(
                  Icons.delete_outline,
                  size: 18,
                  color: Theme.of(context).colorScheme.error,
                ),
          onPressed: () {
            if (state is! DeleteFAQProgress) {
              Utils.openAlertDialog(context, onTapYes: () {
                context.read<DeleteFAQCubit>().deleteFAQ(
                    faqId: int.parse(widget.faq.id),
                    type: widget.faq.type ?? 'regular');
                Navigator.of(context).pop();
              }, message: deleteFaqConfirmationKey);
            }
          },
        );
      },
    );
  }
}
