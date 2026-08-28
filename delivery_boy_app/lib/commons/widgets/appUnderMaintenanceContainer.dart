import 'package:eshoppro_deliveryboy/commons/blocs/settingsAndLanguagesCubit.dart';
import 'package:eshoppro_deliveryboy/commons/widgets/customTextContainer.dart';
import 'package:eshoppro_deliveryboy/core/localization/labelKeys.dart';
import 'package:eshoppro_deliveryboy/utils/utils.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

class AppUnderMaintenanceContainer extends StatelessWidget {
  const AppUnderMaintenanceContainer({Key? key}) : super(key: key);

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Utils.setSvgImage("under_maintenance"),
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
                        .messageForDeliveryBoyApp ??
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
    );
  }
}
