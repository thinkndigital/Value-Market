import 'package:value_market_customer/commons/blocs/settingsAndLanguagesCubit.dart';
import 'package:value_market_customer/commons/widgets/customTextContainer.dart';
import 'package:value_market_customer/core/localization/labelKeys.dart';
import 'package:value_market_customer/utils/utils.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

class AppUnderMaintenanceContainer extends StatelessWidget {
  const AppUnderMaintenanceContainer({Key? key}) : super(key: key);

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Center(
        child: SingleChildScrollView(
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              SizedBox(
                  height: 400,
                  child:
                      FittedBox(child: Utils.setSvgImage("under_maintenance"))),
              SizedBox(
                height: MediaQuery.of(context).size.height * (0.0125),
              ),
              Padding(
                padding: const EdgeInsets.all(20),
                child: CustomTextContainer(
                  textKey: context
                          .read<SettingsAndLanguagesCubit>()
                          .getSettings()
                          .systemSettings!
                          .messageForCustomerApp ??
                      appUnderMaintenanceKey,
                  textAlign: TextAlign.center,
                  style: Theme.of(context)
                      .textTheme
                      .titleLarge!
                      .copyWith(color: Theme.of(context).colorScheme.secondary),
                ),
              )
            ],
          ),
        ),
      ),
    );
  }
}
