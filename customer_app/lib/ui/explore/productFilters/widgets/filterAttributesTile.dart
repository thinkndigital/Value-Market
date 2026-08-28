import 'package:eshop_plus/commons/widgets/customTextContainer.dart';
import 'package:flutter/material.dart';

class FilterAttributeTile extends StatelessWidget {
  final String title;
  final int id;
  final bool isSelected;
  final Function(int) onTap;
  const FilterAttributeTile(
      {super.key,
      required this.id,
      required this.title,
      required this.isSelected,
      required this.onTap});

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: () => onTap(id),
      child: Container(
        padding: const EdgeInsetsDirectional.all(10.0),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              width: 18.0,
              height: 18.0,
              decoration: BoxDecoration(
                  borderRadius: BorderRadius.circular(2.5),
                  border: isSelected
                      ? null
                      : Border.all(
                          width: 1.5,
                        ),
                  color: isSelected
                      ? Theme.of(context).colorScheme.primary
                      : null),
              child: isSelected
                  ? Icon(
                      Icons.check,
                      size: 15.0,
                      color: Theme.of(context).colorScheme.onPrimary,
                    )
                  : const SizedBox(),
            ),
            const SizedBox(
              width: 10,
            ),
            Expanded(
              child: CustomTextContainer(
                textKey: title,
                style: Theme.of(context).textTheme.bodyMedium,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
