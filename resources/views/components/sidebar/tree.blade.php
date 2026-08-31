{{--
    Unified Dynamic Sidebar Engine renderer (32-phase SaaS brief, Phase 3).

    Recursive - a node with 'children' renders itself as a collapsible group and recurses; a leaf
    node renders a single <a>. Markup/classes are unchanged from the pre-engine per-role Blade
    sidebars (nav-item / nav-link / sidebar-group-toggle / sidebar-subtitle / collapse / show) so no
    CSS had to change, including the RTL icon-order fix already applied to those classes.

    Props: $items (array from SidebarService::build()), $prefix (string, internal - builds unique
    collapse ids when the same node key appears more than once across a role's tree).
--}}
@props(['items', 'prefix' => ''])

@foreach ($items as $node)
    @php
        $domId = $prefix ? "{$prefix}-{$node['key']}" : $node['key'];
        $labelText = labels($node['label_key'] ?? '', $node['label_fallback'] ?? '');
    @endphp

    @if (!empty($node['is_subtitle']))
        <li class="sidebar-subtitle ms-3">{{ $labelText }}</li>
    @elseif (!empty($node['children']))
        <li class="nav-item ms-3">
            <a data-bs-toggle="collapse" href="#{{ $domId }}"
                class="nav-link {{ !empty($node['icon']) ? 'sidebar-group-toggle' : '' }} {{ $node['active'] ? 'active' : 'collapsed' }}"
                aria-controls="{{ $domId }}" role="button" aria-expanded="false">
                @if (!empty($node['icon']))
                    <i class='{{ $node['icon'] }}'></i>
                @endif
                <span class="nav-link-text ms-1">{{ $labelText }}</span>
                <i class="fas fa-angle-down"></i>
            </a>
            <div class="collapse {{ $node['active'] ? 'show' : '' }}" id="{{ $domId }}">
                <ul class="nav">
                    <x-sidebar.tree :items="$node['children']" :prefix="$domId" />
                </ul>
            </div>
        </li>
    @else
        <li class="nav-item ms-3">
            <a class="nav-link {{ $node['active'] ? 'active' : '' }}" href="{{ route($node['route']) }}">
                @if (!empty($node['icon']))
                    <i class='{{ $node['icon'] }}'></i>
                @endif
                @if (!empty($node['label_html']))
                    <span class="nav-link-text ms-1">{!! $labelText !!}</span>
                @else
                    <span class="nav-link-text ms-1">{{ $labelText }}</span>
                @endif
                @if (!empty($node['badge_count']))
                    <span class="flex-shrink-0 badge badge-center bg-danger w-px-20 h-px-20 ms-1 rounded-pill">{{ $node['badge_count'] }}</span>
                @endif
            </a>
        </li>
    @endif
@endforeach
