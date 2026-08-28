import 'package:eshop_plus/ui/home/brand/brandsCubit.dart';
import 'package:eshop_plus/commons/blocs/storesCubit.dart';
import 'package:eshop_plus/ui/explore/productFilters/widgets/filterAttributesTile.dart';
import 'package:eshop_plus/commons/widgets/customCircularProgressIndicator.dart';
import 'package:eshop_plus/commons/widgets/errorContainer.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

class BrandsFilterView extends StatelessWidget {
  final Function(int) onTapBrand;
  final bool Function(int) isBrandSelected;
  const BrandsFilterView(
      {super.key, required this.onTapBrand, required this.isBrandSelected});

  @override
  Widget build(BuildContext context) {
    return BlocBuilder<BrandsCubit, BrandsState>(
      builder: (context, state) {
        if (state is BrandsFetchSuccess) {
          return ListView.builder(
            padding: const EdgeInsetsDirectional.only(bottom: 100),
            itemCount: state.brands.length,
            itemBuilder: (context, index) {
              final brand = state.brands[index];
              return FilterAttributeTile(
                onTap: onTapBrand,
                id: brand.id ?? 0,
                title: brand.name ?? "",
                isSelected: isBrandSelected.call(brand.id ?? 0),
              );
            },
          );
        }

        if (state is BrandsFetchFailure) {
          return ErrorContainer(
            errorMessage: state.errorMessage,
            onTapRetry: () {
              context.read<BrandsCubit>().getBrands(
                  storeId: context.read<StoresCubit>().getDefaultStore().id!);
            },
          );
        }
        return Center(
          child: CustomCircularProgressIndicator(
            indicatorColor: Theme.of(context).colorScheme.primary,
          ),
        );
      },
    );
  }
}
