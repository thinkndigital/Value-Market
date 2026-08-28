import 'package:dio/dio.dart';
import 'package:eshopplus_seller/commons/blocs/settingsAndLanguagesCubit.dart';
import 'package:eshopplus_seller/commons/models/systemSettings.dart';
import 'package:eshopplus_seller/commons/widgets/customCircularProgressIndicator.dart';
import 'package:eshopplus_seller/commons/widgets/customCuperinoSwitch.dart';
import 'package:eshopplus_seller/commons/widgets/customTextContainer.dart';
import 'package:eshopplus_seller/commons/widgets/customTextFieldContainer.dart';
import 'package:eshopplus_seller/core/localization/labelKeys.dart';
import 'package:eshopplus_seller/utils/designConfig.dart';
import 'package:eshopplus_seller/utils/utils.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

class AiPromptField extends StatefulWidget {
  final TextEditingController descriptionController;
  final TextEditingController productNameController;
  final String selectedLanguage;
  final Function callback;
  final bool isShortDescription;
  const AiPromptField(
      {super.key,
      required this.descriptionController,
      required this.productNameController,
      required this.selectedLanguage,
      required this.callback,
      required this.isShortDescription});

  @override
  State<AiPromptField> createState() => _AiPromptFieldState();
}

class _AiPromptFieldState extends State<AiPromptField> {
  final TextEditingController promptController = TextEditingController();
  bool isLoading = false;
  late SystemSettings settings;

  Future<void> generateDescription() async {
    FocusScope.of(context).unfocus();
    if (widget.productNameController.text.isEmpty) {
      Utils.showSnackBar(message: pleaseEnterProductNameFirstKey);
      return;
    }
    final prompt = promptController.text.trim();
    if (_showPrompt && prompt.isEmpty) {
      Utils.showSnackBar(message: pleaseEnterPromptKey);
      return;
    }

    String input =
        prompt.isNotEmpty ? prompt : widget.productNameController.text;
    String content =
        'Write html  ${widget.isShortDescription ? "short descrption of average 100 characters" : "long description"}for product named  "$input" in language ${widget.selectedLanguage}';
    // 'You are a professional e-commerce content writer. Write a html content that support in rich_editor library in flutter ${widget.isShortDescription ? "short descrption of average 100 characters" : "long description"} for product named  "$input" in language ${widget.selectedLanguage';
    //String content = 'You are a professional e-commerce content writer. Write content that support in ${widget.isShortDescription ? "short descrption of average 100 characters" : "long description"} for product named  "$input" in language ${widget.selectedLanguage}, even if the word is slightly misspelled.';
    setState(() => isLoading = true);
    try {
      final dio = Dio();

      Response response;

      if (settings.AISetting!['ai_method'] == 'openrouter_api') {
        response = await dio.post(
          'https://openrouter.ai/api/v1/chat/completions',
          options: Options(
            headers: {
              'Authorization':
                  'Bearer ${context.read<SettingsAndLanguagesCubit>().getSettings().systemSettings!.AISetting!['openrouter_api_key']}',
              'Content-Type': 'application/json',
              'HTTP-Referer':
                  'https://eshop-pro-dev.eshopweb.store/', // update with your app URL
            },
          ),
          data: {
            'model': 'mistralai/mistral-7b-instruct',
            'messages': [
              {'role': 'user', 'content': content},
            ],
            'max_tokens': 300,
          },
        );
        final aiText = response.data['choices'][0]['message']['content'];
        widget.descriptionController.text = aiText;
        widget.callback(aiText);
      } else {
        response = await dio.post(
          'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=${context.read<SettingsAndLanguagesCubit>().getSettings().systemSettings!.AISetting!['gemini_api_key']}',
          options: Options(headers: {'Content-Type': 'application/json'}),
          data: {
            'contents': [
              {
                'parts': [
                  {'text': content}
                ]
              }
            ]
          },
        );

        final aiText =
            response.data['candidates'][0]['content']['parts'][0]['text'];
        widget.descriptionController.text = aiText;
        widget.callback();
      }
    } catch (e) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Failed to generate description')),
      );
    } finally {
      setState(() => isLoading = false);
    }
  }

  @override
  void initState() {
    // TODO: implement initState
    super.initState();
    Future.delayed(Duration.zero, () {
      settings = context
          .read<SettingsAndLanguagesCubit>()
          .getSettings()
          .systemSettings!;
    });
  }

  bool _showPrompt = false;
  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        DesignConfig.smallHeightSizedBox,
        isLoading
            ? CustomCircularProgressIndicator(
                indicatorColor: Theme.of(context).colorScheme.primary,
              )
            : ElevatedButton(
                onPressed: generateDescription,
                child: const CustomTextContainer(
                    textKey: generateDescriptionWithAIKey)),
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: <Widget>[
            CustomTextContainer(textKey: customPromptKey),
            CustomCuperinoSwitch(
                value: _showPrompt,
                onChanged: (value) {
                  setState(() {
                    _showPrompt = value;
                  });
                }),
          ],
        ),
        if (_showPrompt) ...[
          CustomTextFieldContainer(
            textEditingController: promptController,
            hintTextKey: enterYourOwnAIPromptKey,
            labelKey: '',
          ),
        ],
      ],
    );
  }
}
