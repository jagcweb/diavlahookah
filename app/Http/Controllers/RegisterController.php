<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use App\Models\City;
use App\Models\CityUser;
use App\Models\Image;
use Illuminate\Support\Str;

class RegisterController extends Controller
{
    /**
     * Display a form to create a new user.
     *
     * @return \Illuminate\Http\Response
     */
    public function create($step, $user = null)
    {

        if (!is_null($user)) {
            $id = \Crypt::decryptString($user);
            $user = User::find($id);
            $oneHourAgo = now()->subHour();

            if (!is_null($user->completed)) {
                return redirect()->route('login');
            }
        
            if ($user->created_at < $oneHourAgo) {
                return redirect()->route('login');
            }
        }
        

        $cities = City::orderBy('id', 'asc')->get();

        if(count($cities) == 0){
            return redirect()->route('admin.utilities.zones')->with('error', 'Antes de crear un usuario, debes tener alguna ciudad creada para poder asignarsela.');
        }

        return view('auth.register', [
            'cities' => $cities,
            'step' => $step,
            'user' => $user
        ]);
    }

    /**
     * Store a newly created user in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function save(Request $request, $step, $id = null)
    {
        switch($step) {
            case 'step-1':
                $create = User::create([
                    'full_name' => Str::random(30),
                    'nickname' => $request->nickname,
                    'age' => $request->age,
                    'whatsapp' => $request->whatsapp,
                    'phone' => $request->phone,
                    'is_smoker' => $request->smoker,
                    'working_zone' => $request->working_zone,
                    'service_location' => $request->service_location,
                    'gender' => $request->gender,
                    'dni' => Str::random(8),
                    'dni_file' => Str::random(30),
                    'date_of_birth' => $request->date_of_birth,
                    'email' => Str::random(30).'@gmail.com',
                    'height' => $request->height,
                    'weight' => $request->weight,
                    'bust' => $request->bust,
                    'waist' => $request->waist,
                    'hip' => $request->hip,
                    'start_day' => $request->start_day,
                    'end_day' => $request->end_day,
                    'start_time' => !is_null($request->get('fulltime_time')) ? 'fulltime' : $request->start_time,
                    'end_time' => !is_null($request->get('fulltime_time')) ? 'fulltime' : $request->end_time,
                    'status' => 0,
                    'profile_image' => NULL,
                    'active' => NULL,
                    'frozen' => NULL,
                    'visible' => NULL,
                    'online' => NULL,
                    'email_verified_at' => NULL,
                    'banned' => NULL,
                    'password' => Str::random(30),
                    'completed' => NULL
                ]);

                $create->assignRole('user');

                foreach($request->get('city') as $city){
                    $city_user = new CityUser();
                    $city_user->city_id = $city;
                    $city_user->user_id = $create->id;
                    $city_user->save();
                }

                return redirect()->route('user.register', ['step' => '2', 'user' => \Crypt::encryptString($create->id)])->with('exito', 'Paso 1 completado. Datos guardados.');
            break;

            case 'step-2':

                $request->validate([
                    'files.*' => 'required|file|mimes:png,jpeg,jpg,webp,gif,bmp,avi,mp4,mpg,mov,3gp,wmv,flv|max:10240', // 10MB
                    'files' => 'array|max:8', // Maximum 8 files
                ]);
            
                $id = \Crypt::decryptString($id);
                $user = User::find($id);
                $files = $request->file('files');

                // Process each file
                foreach ($files as $file) {
                    // Upload the image
                    $imageName = time() . '-' . $file->getClientOriginalName();
                    \Storage::disk('images')->put($imageName, \File::get($file));

                    // Create an Image model and save it to the database
                    $image = new Image();
                    $image->user_id = $user->id;
                    $image->route = $imageName;
                    $image->size = round($file->getSize() / 1024, 2); // Size in KB
                    $image->type = "images";
                    $image->status = 'active'; // Set initial status to active
                    $image->save();
                }
        
                return redirect()->route('user.register', ['step' => '3', 'user' => \Crypt::encryptString($user->id)])->with('exito', 'Paso 2 completado. Imágenes subidas.');
            break;

            case 'step-3':
                $id = \Crypt::decryptString($id);
                $user = User::find($id);

                $file = $request->file('dni_file');

                $imageName = time() . $file->getClientOriginalName();
                \Storage::disk('images')->put($imageName, \File::get($file));

                $user->full_name = $request->get('full_name');
                $user->dni = $request->dni;
                $user->dni_file = $imageName;
                $user->email = $request->email;
                $user->password = \Hash::make($request->dni);
                $user->completed = 1;
                $user->update();
                
                return redirect()->route('login')->with('exito', 'Paso 3 completado. Usuario creado.');
            break;

        }

        return redirect()->route('admin.users.getActive')->with('exito', 'Usuario creado');
    }

    public function edit($id) {
        $user = User::find($id);
        $cities = City::orderBy('id', 'asc')->get();
        return view('admin.users.edit', [
            'user' => $user,
            'cities' => $cities
        ]);
    }

    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'full_name' => 'required|string|max:255',
            'dni' => 'required|string|max:20', // Ajusta la longitud según tu país
            'date_of_birth' => 'required|date',
            'email' => 'required|email|unique:users,id',
            'dni_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png',
            'nickname' => 'required|string|max:50|unique:users,id',
            'age' => 'required|numeric|min:18|max:99',
            'whatsapp' => 'required|string|max:20',
            'phone' => 'required|string|max:20',
            'smoker' => 'required|boolean', // Assuming a boolean field for smoker
            'working_zone' => 'required|string',
            'service_location' => 'required|string',
            'gender' => 'required|in:hombre,mujer,otro',
            'height' => 'required|numeric', // Ajusta el rango según tus necesidades
            'weight' => 'required|numeric', // Ajusta el rango según tus necesidades
            'bust' => 'required|numeric',
            'waist' => 'required|numeric',
            'hip' => 'required|numeric',
            'start_day' => 'required|in:fulltime,lunes,martes,miercoles,jueves,viernes,sabado,domingo',
            'end_day' => 'required|in:fulltime,lunes,martes,miercoles,jueves,viernes,sabado,domingo',
            'start_time' => 'nullable|numeric|min:0|max:23',
            'end_time' => 'nullable|numeric|min:0|max:23',
            'fulltime_time' => 'nullable|numeric',
            'user_id' => 'required|integer',
            'city' => 'required|array',
            'city.*' => 'alpha_num',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator->errors());
        }

        if(count($request->get('city')) == 0) {
            return redirect()->back()->with('error', 'Debes seleccionar al menos una ciudad.');
        }

        if(is_null($request->get('start_time')) && is_null($request->get('end_time')) && is_null($request->get('fulltime_time'))) {
            return redirect()->back()->with('error', 'Debes seleccionar un horario.');
        }

        $id = $request->get('user_id');

        $user = User::find($id);

        if(!is_null($request->file('dni_file'))) {
            \Storage::disk('images')->delete($user->dni_file);

            $file = $request->file('dni_file');

            $imageName = time() . $file->getClientOriginalName();
            \Storage::disk('images')->put($imageName, \File::get($file));
        }

        $cities = \App\Models\CityUser::where('user_id', $user->id)->get();

        foreach($cities as $city){
            $city->delete();
        }

        $user->update([
            'full_name' => $request->full_name,
            'nickname' => $request->nickname,
            'age' => $request->age,
            'whatsapp' => $request->whatsapp,
            'phone' => $request->phone,
            'is_smoker' => $request->smoker,
            'working_zone' => $request->working_zone,
            'service_location' => $request->service_location,
            'gender' => $request->gender,
            'dni' => $request->dni,
            'dni_file' => !is_null($request->file('dni_file')) ? $imageName : $user->dni_file,
            'date_of_birth' => $request->date_of_birth,
            'email' => $request->email,
            'height' => $request->height,
            'weight' => $request->weight,
            'bust' => $request->bust,
            'waist' => $request->waist,
            'hip' => $request->hip,
            'start_day' => $request->start_day,
            'end_day' => $request->end_day,
            'start_time' => !is_null($request->get('fulltime_time')) ? 'fulltime' : $request->start_time,
            'end_time' => !is_null($request->get('fulltime_time')) ? 'fulltime' : $request->end_time,
            'updated_at' => \Carbon\Carbon::now()
        ]);

        foreach($request->get('city') as $city){
            $city_user = new CityUser();
            $city_user->city_id = $city;
            $city_user->user_id = $user->id;
            $city_user->save();
        }

        return back()->with('exito', 'Usuario modificado!');
    }

    public function updateStatus(Request $request) {
        $id = $request->get('user_id');

        $user = User::find($id);

        $user->update([
            'active' => $request->active,
            'frozen' => $request->frozen,
            'visible' => $request->visible,
        ]);

        return back()->with('exito', 'Usuario actualizado!');
    }

    public function updatePassword(Request $request, $id)
    {
        $request->validate([  
            'password' => ['required', 'min:8'],
        ]);

        $user = User::find($id);

        $user->update(['password' => Hash::make($request->password)]);

        return back()->with('exito', 'La contraseña se ha actualizado correctamente.');
    }

    /**
     * Get a list of pending users (e.g. not activated).
     *
     * @return \Illuminate\Http\Response
     */
    public function getPending()
    {
        $users = User::whereHas(
            'roles', function($q){
                $q->where('name', 'user');
            })
            ->whereNull('active')->get(); // Replace 0 with your pending user status value

        return view('admin.users.pending', compact('users'));
    }

    /**
     * Get a list of active users.
     *
     * @return \Illuminate\Http\Response
     */
    public function getActive()
    {
        $users = User::whereHas(
            'roles', function($q){
                $q->where('name', 'user');
            })
            ->where('active', 1)->get(); // Replace 1 with your active user status value

        return view('admin.users.active', compact('users'));
    }

        /**
     * Get a list of requests.
     *
     * @return \Illuminate\Http\Response
     */
    public function getRequests()
    {
        $users = User::where('status', 1)->get(); // Replace 1 with your active user status value

        return view('admin.users.active', compact('users'));
    }

    /**
     * Get a list of user login records.
     *
     * @return \Illuminate\Http\Response
     */
    public function getLoginRecords()
    {
        // You might need to implement login history functionality or use an existing package

        return view('admin.users.login-records');
    }
}
