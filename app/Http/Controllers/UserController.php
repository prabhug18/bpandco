<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Validation\Rules;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');
        $role = $request->input('role');

        $users = User::with('roles')
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($role, function ($query, $role) {
                $query->whereHas('roles', function ($q) use ($role) {
                    $q->where('name', $role);
                });
            })
            ->orderBy('name')
            ->get();

        return Inertia::render('Users/Index', [
            'users' => $users,
            'filters' => $request->only(['search', 'role']),
            'roles' => Role::all(),
        ]);
    }

    public function create()
    {
        return Inertia::render('Users/Create', [
            'roles' => Role::all(),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'mobile'   => 'nullable|digits:10',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'role'     => 'required|exists:roles,name',
        ]);

        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'mobile'   => $request->mobile,
            'password' => Hash::make($request->password),
        ]);

        $user->assignRole($request->role);

        return redirect()->route('users.index')->with('success', "User '{$user->name}' created successfully.");
    }

    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->withErrors(['error' => 'You cannot delete your own account.']);
        }

        $user->delete();

        return redirect()->route('users.index')->with('success', "User '{$user->name}' has been removed.");
    }

    public function edit(string $id)
    {
        $user = User::with(['roles', 'permissions'])->findOrFail($id);
        return Inertia::render('Users/Edit', [
            'userToEdit'  => $user,
            'roles'       => Role::all(),
            'permissions' => \Spatie\Permission\Models\Permission::all()
        ]);
    }

    public function update(Request $request, string $id)
    {
        $user = User::findOrFail($id);

        $request->validate([
            'name'          => 'required|string|max:255',
            'email'         => 'required|email|unique:users,email,' . $user->id,
            'mobile'        => 'nullable|digits:10',
            'password'      => ['nullable', 'confirmed', Rules\Password::defaults()],
            'roles'         => 'nullable|array',
            'roles.*'       => 'exists:roles,name',
            'permissions'   => 'nullable|array',
            'permissions.*' => 'exists:permissions,name',
        ]);

        $user->update([
            'name'   => $request->name,
            'email'  => $request->email,
            'mobile' => $request->mobile,
        ]);

        if ($request->filled('password')) {
            $user->update(['password' => Hash::make($request->password)]);
        }

        $user->syncRoles($request->input('roles', []));
        $user->syncPermissions($request->input('permissions', []));

        return redirect()->route('users.index')->with('success', "User '{$user->name}' updated successfully.");
    }

    public function export(Request $request)
    {
        $search = $request->input('search');
        $role = $request->input('role');

        $users = User::with('roles')
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($role, function ($query, $role) {
                $query->whereHas('roles', function ($q) use ($role) {
                    $q->where('name', $role);
                });
            })
            ->orderBy('name')
            ->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Header
        $sheet->setCellValue('A1', 'Employee Name');
        $sheet->setCellValue('B1', 'Email');
        $sheet->setCellValue('C1', 'Mobile');
        $sheet->setCellValue('D1', 'Roles');

        // Style header
        $sheet->getStyle('A1:D1')->getFont()->setBold(true);

        // Data
        $row = 2;
        foreach ($users as $user) {
            $sheet->setCellValue('A' . $row, $user->name);
            $sheet->setCellValue('B' . $row, $user->email);
            $sheet->setCellValue('C' . $row, $user->mobile ?? 'N/A');
            $sheet->setCellValue('D' . $row, $user->roles->pluck('name')->implode(', '));
            $row++;
        }

        $writer = new Xlsx($spreadsheet);
        $fileName = 'staff_export_' . now()->format('Y-m-d_His') . '.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        $writer->save('php://output');
        exit;
    }
}
