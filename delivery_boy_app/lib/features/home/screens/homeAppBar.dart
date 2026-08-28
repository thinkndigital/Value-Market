import 'package:value_market_delivery_boy/core/constants/themeConstants.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import '../../../core/localization/labelKeys.dart';
import '../../../../utils/utils.dart';
import '../../../core/routes/routes.dart';
import '../../../commons/blocs/userDetailsCubit.dart';
import '../../../commons/widgets/customTextContainer.dart';

class HomeAppBar extends StatelessWidget implements PreferredSizeWidget {
  const HomeAppBar({
    super.key,
  });

  @override
  Widget build(BuildContext context) {
    return BlocBuilder<UserDetailsCubit, UserDetailsState>(
      builder: (context, state) {
        return PreferredSize(
          preferredSize: const Size.fromHeight(80),
          child: Container(
            decoration: const BoxDecoration(color: Colors.white, boxShadow: [
              BoxShadow(
                  color: Color.fromRGBO(0, 0, 0, 0.08),
                  offset: Offset(0, 5.0),
                  blurRadius: 9.0,
                  spreadRadius: 0)
            ]),
            padding: const EdgeInsetsDirectional.symmetric(
                horizontal: appContentHorizontalPadding, vertical: 8),
            child: AppBar(
              leadingWidth: 40,
              automaticallyImplyLeading: false,
              titleSpacing: 10,
              elevation: 0,
              backgroundColor: Colors.white,
              surfaceTintColor: Colors.white,
              leading: Align(
                alignment: Alignment.centerLeft,
                child: Utils.buildProfilePicture(
                    context,
                    44,
                    (context.read<UserDetailsCubit>().state
                            as UserDetailsFetchSuccess)
                        .userDetails
                        .image!,
                    selectedFile: null,
                    assetImage: false,
                    outerBorderColor: Colors.grey),
              ),
              title: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  CustomTextContainer(
                    textKey: welcomeKey,
                    style: Theme.of(context).textTheme.bodyLarge,
                  ),
                  CustomTextContainer(
                    textKey: (context.read<UserDetailsCubit>().state
                                as UserDetailsFetchSuccess)
                            .userDetails
                            .username ??
                        "",
                    style: Theme.of(context).textTheme.bodySmall,
                  ),
                ],
              ),
              actions: [
                GestureDetector(
                  onTap: () => Utils.navigateToScreen(
                      context, Routes.notificationScreen),
                  child: const Icon(
                    Icons.notifications_outlined,
                  ),
                ),
              ],
            ),
          ),
        );
      },
    );
  }

  @override
  Size get preferredSize => const Size.fromHeight(80);
}
