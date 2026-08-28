import 'package:eshopplus_seller/commons/widgets/customTextContainer.dart';
import 'package:eshopplus_seller/features/profile/addProduct/models/tax.dart';
import 'package:flutter/material.dart';

import '../../../../../core/localization/labelKeys.dart';

class TaxSelectionDialog extends StatefulWidget {
  final Map<String, String> selectedtax;
  final List<Tax> taxlist;
  final Function selectionCallback;
  const TaxSelectionDialog(
      {super.key,
      required this.selectedtax,
      required this.selectionCallback,
      required this.taxlist});

  @override
  TaxSelectionDialogState createState() => TaxSelectionDialogState();
}

class TaxSelectionDialogState extends State<TaxSelectionDialog> {
  Map<String, String> tempselectedtax = {};
  @override
  void initState() {
    super.initState();
    tempselectedtax.addAll(widget.selectedtax);
  }

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      child: SizedBox(
        width: double.maxFinite,
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          Align(
            alignment: AlignmentDirectional.topEnd,
            child: IconButton(
                onPressed: () {
                  Navigator.of(context).pop();
                },
                icon: const Icon(Icons.cancel_outlined)),
          ),
          ConstrainedBox(
              constraints: BoxConstraints(
                maxHeight: MediaQuery.of(context).size.height * 0.6,
              ),
              child: dropdownListWidget()),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceAround,
            children: [
              TextButton(
                  onPressed: () {
                    Navigator.of(context).pop();
                  },
                  child: const CustomTextContainer(textKey: cancelKey)),
              TextButton(
                  onPressed: () {
                    widget.selectionCallback(tempselectedtax);
                  },
                  child: const CustomTextContainer(textKey: applyKey))
            ],
          )
        ]),
      ),
    );
  }

  dropdownListWidget() {
    return ListView.builder(
      itemCount: widget.taxlist.length,
      itemBuilder: (BuildContext context, int index) {
        Tax tax = widget.taxlist[index];
        String taxval = "${tax.title} (${tax.percentage}%)";
        return CheckboxListTile(
          value: tempselectedtax.containsKey(tax.id.toString()),
          title: Text(taxval),
          dense: true,
          onChanged: (value) {
            if (tempselectedtax.containsKey(tax.id.toString())) {
              tempselectedtax.remove(tax.id.toString());
            } else {
              tempselectedtax[tax.id.toString()] = taxval;
            }
            setState(() {});
          },
        );
      },
    );
  }
}
