import 'package:value_market_seller/commons/widgets/customBottomButtonContainer.dart';
import 'package:value_market_seller/commons/widgets/customRoundedButton.dart';
import 'package:value_market_seller/commons/widgets/customTextContainer.dart';
import 'package:value_market_seller/core/api/apiService.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:rich_editor/rich_editor.dart';

class DescSetupPage extends StatefulWidget {
  final String currText;
  final String title;
  final Function(String)? callback;

  const DescSetupPage({
    super.key,
    required this.currText,
    required this.title,
    this.callback,
  });
  static Widget getRouteInstance() => DescSetupPage(
        callback: Get.arguments['callback'],
        currText: Get.arguments['currText'] ?? '',
        title: Get.arguments['title'] ?? '',
      );
  @override
  State<DescSetupPage> createState() => _DescSetupPageState();
}

class _DescSetupPageState extends State<DescSetupPage> {
  GlobalKey<RichEditorState> keyEditor = GlobalKey();

  @override
  void initState() {
    super.initState();
    Api.printLongString('==value====${widget.currText}');
    Future.delayed(const Duration(seconds: 2), () {
      keyEditor.currentState.printInfo();
      keyEditor.currentWidget.printError();
    });
  }

  void getHtmlText() async {
    String? html = await keyEditor.currentState?.getHtml();
    if (widget.callback != null && html != null) {
      widget.callback!(html);
    }
    Navigator.of(context).pop();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: CustomTextContainer(textKey: widget.title),
        actions: [
          IconButton(
            icon: Icon(Icons.check),
            onPressed: getHtmlText,
          )
        ],
      ),
      body: Column(
        children: [
          Expanded(
            child: RichEditor(
              value: widget.currText,
              key: keyEditor,

              editorOptions: RichEditorOptions(
                placeholder: widget.title,
                baseTextColor: Colors.white,
                padding: const EdgeInsets.all(10),
                enableVideo: true,
                barPosition: BarPosition.TOP,
              ),
              // Configure styles if needed
            ),
          ),
          appbarBtns()
        ],
      ),
    );
  }

  appbarBtns() {
    return CustomBottomButtonContainer(
        child: Row(
      children: <Widget>[
        Expanded(
          child: CustomRoundedButton(
            widthPercentage: 1,
            buttonTitle: 'Clear',
            showBorder: true,
            backgroundColor: Theme.of(context).colorScheme.onPrimary,
            borderColor: Theme.of(context).inputDecorationTheme.iconColor,
            style: Theme.of(context)
                .textTheme
                .titleMedium!
                .copyWith(color: Theme.of(context).colorScheme.secondary),
            onTap: () async {
              await keyEditor.currentState?.unFocus();
              await keyEditor.currentState?.clear();
            },
          ),
        ),
        const SizedBox(
          width: 16,
        ),
        Expanded(
          child: CustomRoundedButton(
            widthPercentage: 1,
            buttonTitle: 'Done',
            showBorder: false,
            onTap: getHtmlText,
          ),
        ),
      ],
    ));
  }
}
