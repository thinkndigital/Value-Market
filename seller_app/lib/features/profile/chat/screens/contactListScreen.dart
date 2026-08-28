import 'package:value_market_seller/commons/widgets/safeAreaWithBottomPadding.dart';
import 'package:value_market_seller/core/constants/themeConstants.dart';
import 'package:value_market_seller/core/routes/routes.dart';
import 'package:value_market_seller/features/profile/chat/blocs/getContactsCubit.dart';
import 'package:value_market_seller/commons/models/userDetails.dart';
import 'package:value_market_seller/commons/widgets/customAppbar.dart';
import 'package:value_market_seller/commons/widgets/customCircularProgressIndicator.dart';
import 'package:value_market_seller/commons/widgets/customDefaultContainer.dart';
import 'package:value_market_seller/commons/widgets/customSearchContainer.dart';
import 'package:value_market_seller/commons/widgets/customTextContainer.dart';
import 'package:value_market_seller/commons/widgets/errorScreen.dart';

import 'package:value_market_seller/utils/designConfig.dart';
import 'package:value_market_seller/core/localization/labelKeys.dart';
import 'package:value_market_seller/utils/utils.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

class ContactListScreen extends StatefulWidget {
  const ContactListScreen({Key? key}) : super(key: key);
  static Widget getRouteInstance() => BlocProvider(
        create: (context) => GetContactsCubit(),
        child: const ContactListScreen(),
      );
  @override
  _ContactListScreenState createState() => _ContactListScreenState();
}

class _ContactListScreenState extends State<ContactListScreen> {
  List<UserDetails> users = [], admins = [];
  final _searchController = TextEditingController();
  String prevVal = '';
  @override
  void initState() {
    super.initState();
    getUsers();
  }

  void getUsers({String searchval = ""}) {
    context.read<GetContactsCubit>().getContactss(
          search: searchval,
        );
  }

  @override
  void dispose() {
    super.dispose();
    _searchController.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: const CustomAppbar(titleKey: chatKey),
      body: SafeAreaWithBottomPadding(
        child: BlocListener<GetContactsCubit, GetContactsState>(
          listener: (context, state) {
            if (state is GetContactsSuccess) {
              //             role id 1 - super admin
              // 2 - users
              //4 - seller // not included
              // 5 - admin
              users = state.users.where((user) => user.roleId == 2).toList();
              admins = state.users
                  .where((user) => user.roleId == 1 || user.roleId == 5)
                  .toList();
            }
          },
          child: Padding(
            padding: const EdgeInsets.symmetric(vertical: 12),
            child: Column(
              children: <Widget>[
                CustomDefaultContainer(
                  child: CustomSearchContainer(
                      textEditingController: _searchController,
                      autoFocus: false,
                      showVoiceIcon: false,
                      hintTextKey: searchKey,
                      suffixWidget: IconButton(
                        icon: Icon(
                          Icons.close,
                          color: Theme.of(context).colorScheme.secondary,
                        ),
                        onPressed: () {
                          setState(() {
                            _searchController.clear();
                            getUsers();
                            FocusScope.of(context).unfocus();
                          });
                        },
                      ),
                      onChanged: (val) {
                        searchChange(val);
                      }),
                ),
                buildSellerList()
              ],
            ),
          ),
        ),
      ),
    );
  }

  buildSellerList() {
    return Expanded(
      child: Padding(
        padding:
            const EdgeInsets.symmetric(horizontal: appContentHorizontalPadding),
        child: BlocBuilder<GetContactsCubit, GetContactsState>(
            builder: (context, state) {
          if (state is GetContactsSuccess) {
            if (users.isEmpty && admins.isEmpty) {
              return ErrorScreen(
                text: dataNotAvailableKey,
                onPressed: () {
                  getUsers();
                },
              );
            }
            return ListView(
              children: <Widget>[
                if (users.isNotEmpty) buildList(usersKey, users),
                if (admins.isNotEmpty) buildList(adminKey, admins),
              ],
            );
          }
          if (state is GetContactsFailure) {
            return ErrorScreen(
              text: state.errorMessage,
              onPressed: () {
                getUsers();
              },
            );
          }
          return Center(
            child: CustomCircularProgressIndicator(
              indicatorColor: Theme.of(context).colorScheme.primary,
            ),
          );
        }),
      ),
    );
  }

  buildList(String title, List<UserDetails> users) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        DesignConfig.defaultHeightSizedBox,
        CustomTextContainer(
          textKey: title,
          style: Theme.of(context).textTheme.titleMedium,
        ),
        DesignConfig.smallHeightSizedBox,
        ListView.separated(
          separatorBuilder: (context, index) =>
              DesignConfig.smallHeightSizedBox,
          shrinkWrap: true,
          itemCount: users.length,
          physics: const NeverScrollableScrollPhysics(),
          itemBuilder: (context, index) {
            return buildUserContainer(
              users[index],
            );
          },
        )
      ],
    );
  }

  buildUserContainer(UserDetails user) {
    return GestureDetector(
      onTap: () => Utils.navigateToScreen(context, Routes.chatScreen,
          arguments: {'id': user.id, 'userName': user.username}),
      child: CustomDefaultContainer(
          borderRadius: 8,
          child: Row(
            children: <Widget>[
              Utils.buildProfilePicture(
                context,
                48,
                user.image ?? '',
                outerBorderColor: Colors.transparent,
              ),
              const SizedBox(
                width: 12,
              ),
              Expanded(
                child: CustomTextContainer(
                  textKey: user.username ?? '',
                  maxLines: 2,
                  style: Theme.of(context).textTheme.titleMedium,
                ),
              ),
            ],
          )),
    );
  }

  searchChange(String val) {
    if (val.trim() != prevVal) {
      Future.delayed(Duration.zero, () {
        prevVal = val.trim();
        Future.delayed(const Duration(seconds: 1), () {
          getUsers(searchval: val);
        });
      });
    }
  }
}
